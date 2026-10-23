#!/usr/bin/env bash
# READ-ONLY inventory of the document root of each app, run ON the host (piped over ssh by
# .github/workflows/app-folder-guard.yml, mode docroot-inventory) and in tools/test-docroot-inventory.sh against a temp
# HOME. The tested code is the deployed code.
#
#   docroot-inventory-host.sh <host>:<key> [...]
#
# Why: the owner will move each subdomain's document root from ~/<host>.infodot.co.za/ to
# ~/<host>.infodot.co.za/<key>/public so .env, databases, vendor/ and logs are never under the web root. This script says,
# per app, what that move depends on: the docroot index.php shim, the two .htaccess files, symlinks, uploads, cron.
#
# READ-ONLY GUARANTEE. This script never writes, creates, removes, renames or changes the mode of anything, never takes a
# lock, and never writes the crontab. Commands used: shell test operators and globbing, stat, readlink, head, tr, awk, grep,
# find (no -exec, no -delete), du, wc, sort, cut, and `crontab -l` (read) once, with a retry. No temp files, no
# here-documents, no output redirection except to /dev/null and to stderr; tools/test-docroot-inventory.sh greps this
# file for anything else and compares a checksum of the whole temp HOME before and after.
#
# NAMES, SIZES AND MODES ONLY. File contents are never printed: .env is never opened; index.php is compared inside the
# script and only the verdict (standard/custom) is printed; when the docroot index.php shim is custom, a SANITISED
# classification is printed too (shim_extra: lines, at most 40 per pair, and shim_* flags: which constructs it uses by name,
# the relative path of each require/include with its query string cut and sensitive-looking pieces redacted, never a string
# literal, a comment or an array, and a verdict SHIM-SAFE-TO-DROP | SHIM-DOES-SOMETHING(flags)); .htaccess files are read for
# DIRECTIVE NAMES from a fixed list of Apache directive names (anything else is counted as "unrecognised", never printed);
# the crontab is only counted.
#
# Output: lines "<host>:<key>: <field>: <value>", then "<host>:<key>: verdict: READY | NEEDS-ATTENTION(<reasons>) |
# UNKNOWN-LAYOUT(<reason>)" per pair and one "summary:" line at the end. Exit: 0 (the verdict is in the output),
# 2 bad input.
set -uo pipefail
export LC_ALL=C

BASE="${BASE_DOMAIN:-infodot.co.za}"
CRON_WAIT="${INVENTORY_CRON_RETRY_WAIT:-1}"
case "$CRON_WAIT" in ''|*[!0-9]*) echo "INVENTORY_CRON_RETRY_WAIT must be a whole number" >&2; exit 2 ;; esac
[ $# -gt 0 ] || { echo "usage: $0 host:key [...]" >&2; exit 2; }
for pair in "$@"; do
  printf '%s' "$pair" | grep -Eq '^[a-z0-9-]+:[a-z0-9-]+$' || { echo "bad pair [$pair]: expected host:key, each [a-z0-9-]+" >&2; exit 2; }
done

HAVE_TIMEOUT=""; command -v timeout >/dev/null 2>&1 && HAVE_TIMEOUT=1
TO() { local s="$1"; shift; if [ -n "$HAVE_TIMEOUT" ]; then timeout "$s" "$@"; else "$@"; fi; }

PAIR=""
out() { printf '%s: %s\n' "$PAIR" "$*"; }
TL='~'
disp() { case "$1" in "$HOME") printf '%s' "$TL" ;; "$HOME"/*) printf '%s' "$TL/${1#"$HOME"/}" ;; *) printf '%s' "$1" ;; esac; }
trunc() { if [ "${#1}" -gt 160 ]; then printf '%s...' "${1:0:160}"; else printf '%s' "$1"; fi; }
yn() { if [ "$1" = yes ]; then printf yes; else printf no; fi; }
ftype() { # file | dir | symlink | broken-symlink | other | missing
  if [ -L "$1" ]; then if [ -e "$1" ]; then echo symlink; else echo broken-symlink; fi
  elif [ -d "$1" ]; then echo dir
  elif [ -f "$1" ]; then echo file
  elif [ -e "$1" ]; then echo other
  else echo missing; fi
}
present() { [ -e "$1" ] || [ -L "$1" ]; }
fsize() { stat -c %s "$1" 2>/dev/null || stat -f %z "$1" 2>/dev/null || echo "?"; }
ignorable() { case "$1" in index.php|.htaccess|.htaccess.*|cgi-bin|.well-known|error_log|.user.ini|php.ini|.ftpquota) return 0 ;; esac; return 1; }

# ---- the shared crontab: read once (never written, never locked); an empty answer is retried, then "unknown" ----
CRON_STATE=""; CRON_TEXT=""
cron_load() {
  [ -n "$CRON_STATE" ] && return 0
  CRON_STATE=unknown
  command -v crontab >/dev/null 2>&1 || return 0
  local i=1 t
  while [ "$i" -le 3 ]; do
    t="$(TO 10 crontab -l 2>/dev/null)"
    if [ -n "$t" ]; then CRON_TEXT="$t"; CRON_STATE="read"; return 0; fi
    if [ "$i" -lt 3 ] && [ "$CRON_WAIT" -gt 0 ]; then sleep "$CRON_WAIT"; fi
    i=$((i + 1))
  done
  return 0
}
count_lines() { # $1 = text, $2 = fixed string, $3 = optional fixed string the line must NOT contain; comment lines are ignored
  local n
  if [ -n "${3:-}" ]; then
    n="$(printf '%s\n' "$1" | grep -v '^[[:space:]]*#' | grep -F -- "$2" | grep -vcF -- "$3")" || true
  else
    n="$(printf '%s\n' "$1" | grep -v '^[[:space:]]*#' | grep -cF -- "$2")" || true
  fi
  printf '%s' "${n:-0}"
}

# ---- .htaccess: directive NAMES from a fixed list, plus yes/no flags. Never prints a line, an argument or a value. ----
# The awk program has no output redirection and no system(); ">" is built with sprintf so the file holds no stray angle bracket.
HT_AWK='
BEGIN {
  GT = sprintf("%c", 62)
  nd = split("RewriteEngine RewriteCond RewriteRule RewriteBase RewriteOptions RewriteMap Header RequestHeader Options Require Order Allow Deny Satisfy ErrorDocument php_value php_flag php_admin_value php_admin_flag AddHandler SetHandler RemoveHandler AddType RemoveType AddCharset AddDefaultCharset AddEncoding AddLanguage AddOutputFilterByType SetOutputFilter SetEnv SetEnvIf SetEnvIfNoCase PassEnv UnsetEnv BrowserMatch Redirect RedirectMatch RedirectPermanent RedirectTemp Alias DirectoryIndex DirectorySlash ExpiresActive ExpiresDefault ExpiresByType FileETag AuthType AuthName AuthUserFile AuthGroupFile ForceType Action IndexIgnore IndexOptions LimitRequestBody ContentDigest CacheEnable SecRuleEngine SecRule SecFilterEngine suPHP_ConfigPath ModPagespeed Include IncludeOptional LogLevel CheckSpelling CheckCaseOnly", D, " ")
  for (i = 1; i <= nd; i++) KD[tolower(D[i])] = D[i]
  nt = split("IfModule IfDefine IfVersion If ElseIf Else Files FilesMatch Directory DirectoryMatch Limit LimitExcept RequireAll RequireAny RequireNone Location LocationMatch VirtualHost", T, " ")
  for (i = 1; i <= nt; i++) KT[tolower(T[i])] = T[i]
  guard = 0
}
{
  line = $0; sub(/\r$/, "", line)
  if (index(tolower(line), "cpanel-generated handler") != 0) cpanel = "yes"
  t = line; sub(/^[ \t]+/, "", t)
  if (index(t, "# BEGIN dot-app-folder-guard ") == 1) { guard = 1; hasguard = "yes"; next }
  if (guard) { if (index(t, "# END dot-app-folder-guard ") == 1) guard = 0; next }
  if (t == "" || substr(t, 1, 1) == "#") next
  lt = tolower(t)
  if (lt ~ /http_authorization/ || lt ~ /http:authorization/) auth = "yes"
  if (substr(t, 1, 2) == "</") next
  if (substr(t, 1, 1) == "<") {
    if (match(t, /^<[A-Za-z]+/)) {
      k = tolower(substr(t, 2, RLENGTH - 1))
      if (k in KT) c["<" KT[k] GT]++; else unrec++
    } else unrec++
    next
  }
  if (match(t, /^[A-Za-z_][A-Za-z0-9_]*/)) k = tolower(substr(t, 1, RLENGTH)); else { unrec++; next }
  if (!(k in KD)) { unrec++; next }
  n = KD[k]; c[n]++
  if (n == "RewriteRule" || n == "RewriteCond") { if (lt ~ /https/ || lt ~ /server_port/) https = "yes" }
  if (n == "Header" || n == "RequestHeader") headers = "yes"
  if (n == "AddHandler" || n == "SetHandler" || n ~ /^php_/ || (n == "AddType" && lt ~ /php/)) php = "yes"
}
END {
  m = 0; for (n in c) names[++m] = n
  for (i = 2; i <= m; i++) { v = names[i]; j = i - 1; while (1 <= j && v < names[j]) { names[j + 1] = names[j]; j-- } names[j + 1] = v }
  s = ""; l = ""
  for (i = 1; i <= m; i++) { sep = (1 < i) ? " " : ""; s = s sep names[i] "=" c[names[i]]; l = l sep names[i] }
  printf "N=%s|L=%s|https=%s|headers=%s|php=%s|auth=%s|cpanel=%s|guard=%s|unrec=%d|end\n", s, l, (https == "yes" ? "yes" : "no"), (headers == "yes" ? "yes" : "no"), (php == "yes" ? "yes" : "no"), (auth == "yes" ? "yes" : "no"), (cpanel == "yes" ? "yes" : "no"), (hasguard == "yes" ? "yes" : "no"), unrec
}'
HT_N=""; HT_L=""; HT_HTTPS=no; HT_HEADERS=no; HT_PHP=no; HT_AUTH=no; HT_CPANEL=no; HT_GUARD=no; HT_UNREC=0
ht_scan() { # $1 = file; sets HT_*; a missing file gives all-empty / "no"
  local res f oldifs="$IFS" file="$1"
  HT_N=""; HT_L=""; HT_HTTPS=no; HT_HEADERS=no; HT_PHP=no; HT_AUTH=no; HT_CPANEL=no; HT_GUARD=no; HT_UNREC=0
  [ -f "$file" ] || return 0
  res="$(awk "$HT_AWK" "$file" 2>/dev/null)"
  set -f; IFS='|'
  # shellcheck disable=SC2086
  set -- $res
  IFS="$oldifs"; set +f
  for f in "$@"; do
    case "$f" in
      N=*) HT_N="${f#N=}" ;; L=*) HT_L="${f#L=}" ;; https=*) HT_HTTPS="${f#https=}" ;; headers=*) HT_HEADERS="${f#headers=}" ;;
      php=*) HT_PHP="${f#php=}" ;; auth=*) HT_AUTH="${f#auth=}" ;; cpanel=*) HT_CPANEL="${f#cpanel=}" ;;
      guard=*) HT_GUARD="${f#guard=}" ;; unrec=*) HT_UNREC="${f#unrec=}" ;;
    esac
  done
}
print_ht() { # $1 = label (docroot|public), $2 = file
  local l="$1" f="$2"
  if [ -f "$f" ]; then out "htaccess_${l}: present size=$(fsize "$f")"; else out "htaccess_${l}: missing"; return 0; fi
  out "htaccess_${l}_directives: ${HT_N:-none}"
  out "htaccess_${l}_unrecognised_lines: $HT_UNREC"
  out "htaccess_${l}_has_https_redirect: $HT_HTTPS"
  out "htaccess_${l}_has_headers: $HT_HEADERS"
  out "htaccess_${l}_has_php_handler_lines: $HT_PHP"
  out "htaccess_${l}_has_auth_header_passthrough: $HT_AUTH"
  out "htaccess_${l}_cpanel_php_block: $HT_CPANEL"
  out "htaccess_${l}_dot_guard_block: $HT_GUARD"
}

# ---- the custom docroot index.php shim: a SANITISED classification, never the content ----
# The PHP text is tokenised by the awk program below; comments are dropped, and string literals are used only to build a
# require/include path (the query string is cut, sensitive-looking segments are redacted); a literal is never printed
# otherwise. Output lines: E|<text> (extra statement), F|<name>|<value> (flag), V|<verdict>. The program has no output
# redirection, no system() and no file access; it only reads standard input (the first 64 KiB of the shim, with every
# tab and CR turned into a space and every other byte that is not printable ASCII or a newline turned into ?).
SHIM_AWK='
BEGIN {
  GT = sprintf("%c", 62); SQ = sprintf("%c", 39); DQ = sprintf("%c", 34); BS = sprintf("%c", 92); BT = sprintf("%c", 96); HDOC = sprintf("%c%c%c", 60, 60, 60)
  RD = "<redacted" GT
  PHE = "%expr%"; PHV = "%var%"
  n = split("ini_set ini_alter ini_restore putenv set_include_path error_reporting date_default_timezone_set set_time_limit setlocale mb_internal_encoding ignore_user_abort umask", L, " "); for (i = 1; i <= n; i++) INI[L[i]] = 1
  n = split("header header_remove http_response_code setcookie setrawcookie header_register_callback", L, " "); for (i = 1; i <= n; i++) HDR[L[i]] = 1
  n = split("require require_once include include_once", L, " "); for (i = 1; i <= n; i++) REQW[L[i]] = 1
  n = split("declare namespace use endif endwhile endfor endforeach endswitch", L, " "); for (i = 1; i <= n; i++) NEU[L[i]] = 1
  n = split("if elseif while for foreach switch catch", L, " "); for (i = 1; i <= n; i++) CTL[L[i]] = 1
  n = split("$_GET $_POST $_COOKIE $_REQUEST $_FILES $_SESSION $GLOBALS", L, " "); for (i = 1; i <= n; i++) SG[L[i]] = 1
  HOME = ENVIRON["HOME"]; DOCROOT = ENVIRON["SH_DOCROOT"]; APP = ENVIRON["SH_APP"]; AF = ENVIRON["SH_AF"]
  SZ = ENVIRON["SH_SIZE"] + 0; PUBMAINT = ENVIRON["SH_PUBMAINT"]
  LN = 1; LP = 1
}
{ T = T $0 "\n" }
function lnat(p,   tmp) { if (LP < p) { tmp = substr(T, LP, p - LP); LN += gsub(/\n/, "", tmp); LP = p } return LN }
function tadd(kind, text, pos) { NT++; TK[NT] = kind; TV[NT] = text; TL[NT] = lnat(pos) }
function isw(c) { return (c ~ /[A-Za-z0-9_]/) }
function lex(   n, i, c, d, j, q, s, pre, rest, nx, w) {
  n = length(T); i = 1; php = 0
  while (i <= n) {
    if (!php) {
      j = index(substr(T, i), "<?")
      if (j == 0) { rest = substr(T, i); if (rest ~ /[^ \n]/) { tadd("H", "", i); tadd("P", ";", i) }; i = n + 1; continue }
      pre = substr(T, i, j - 1); if (pre ~ /[^ \n]/) { tadd("H", "", i); tadd("P", ";", i) }
      i = i + j - 1
      if (substr(T, i, 5) == "<?php") i += 5
      else if (substr(T, i, 3) == "<?=") { tadd("W", "echo", i); i += 3 }
      else i += 2
      php = 1; continue
    }
    c = substr(T, i, 1)
    if (c == " " || c == "\n") { i++; continue }
    nx = substr(T, i + 1, 1)
    if (c == "#" || (c == "/" && nx == "/")) {
      j = i
      while (j <= n) { d = substr(T, j, 1); if (d == "\n") break; if (d == "?" && substr(T, j + 1, 1) == GT) break; j++ }
      i = j; continue
    }
    if (c == "/" && nx == "*") {
      j = index(substr(T, i + 2), "*/")
      if (j == 0) { UNTERM = 1; i = n + 1 } else i = i + j + 3
      continue
    }
    if (c == "?" && nx == GT) { tadd("P", "?" GT, i); i += 2; if (substr(T, i, 1) == "\n") i++; php = 0; continue }
    if (c == SQ || c == DQ) {
      q = c; j = i + 1; s = ""
      while (j <= n) {
        d = substr(T, j, 1)
        if (d == BS) { s = s substr(T, j + 1, 1); j += 2; continue }
        if (d == q) break
        s = s d; j++
      }
      if (n < j) UNTERM = 1
      tadd("S", s, i); TQ[NT] = q; i = j + 1; continue
    }
    if (c == BT) {
      j = index(substr(T, i + 1), BT)
      tadd("B", "", i); if (j == 0) { UNTERM = 1; i = n + 1 } else i = i + j + 1
      continue
    }
    if (c == "<" && substr(T, i, 3) == HDOC) { tadd("X", "", i); STOPPED = 1; i = n + 1; continue }
    if (c == "$") {
      j = i + 1; while (j <= n && isw(substr(T, j, 1))) j++
      if (i + 1 < j) tadd("V", substr(T, i, j - i), i); else tadd("P", "$", i)
      i = j; continue
    }
    if (c ~ /[A-Za-z_]/) {
      j = i + 1; while (j <= n && isw(substr(T, j, 1))) j++
      tadd("W", substr(T, i, j - i), i); i = j; continue
    }
    if (c ~ /[0-9]/) {
      j = i + 1; while (j <= n && isw(substr(T, j, 1))) j++
      tadd("N", "", i); i = j; continue
    }
    if (c == "=" || c == "!" || c == "<" || c == GT) {
      if (nx == "=") { j = i + 2; if (substr(T, j, 1) == "=") j++; tadd("P", "cmp", i); i = j; continue }
      if (c == "=" && nx == GT) { tadd("P", "=" GT, i); i += 2; continue }
    }
    tadd("P", c, i); i++
  }
}
function group_end(k, e,   d, j) {
  d = 0
  for (j = k; j <= e; j++) {
    if (TK[j] == "P") {
      if (TV[j] == "(" || TV[j] == "[") d++
      else if (TV[j] == ")" || TV[j] == "]") { d--; if (d == 0) return j }
    }
  }
  return e
}
function collapse(p,   m, parts, np, i, s, r, stack) {
  np = split(p, parts, "/"); m = 0
  for (i = 1; i <= np; i++) {
    s = parts[i]
    if (s == "" || s == ".") continue
    if (s == "..") { if (0 < m) m--; continue }
    m++; stack[m] = s
  }
  r = ""; for (i = 1; i <= m; i++) r = r "/" stack[i]
  return r
}
function rd_seg(s,   l) {
  gsub(/[^A-Za-z0-9._~%()=+@:,*$ -]/, "?", s)
  l = tolower(s)
  if (l ~ /key|secret|passw|token|pwd|credential|auth/) return RD
  if (19 < length(s) && s ~ /^[A-Za-z0-9+=_-]+$/) return RD
  if (15 < length(s) && s ~ /^[0-9A-Fa-f]+$/) return RD
  if (11 < length(s) && s ~ /^[A-Za-z0-9+=]+$/ && s ~ /[0-9]/ && s ~ /[a-z]/ && s ~ /[A-Z]/) return RD
  return s
}
function rd_path(p,   parts, np, i, r) {
  if (index(p, "://") != 0 || index(p, "@") != 0) return RD
  np = split(p, parts, "/"); r = ""
  for (i = 1; i <= np; i++) r = r (1 < i ? "/" : "") rd_seg(parts[i])
  return r
}
function rd_id(w) { return rd_seg(w) }
function clip(t) {
  gsub(/[^ -~]/, "?", t)
  if (160 < length(t)) t = substr(t, 1, 160) "..."
  return t
}
function interp(v, q) { if (q == DQ) gsub(/\$[A-Za-z_][A-Za-z0-9_]*/, PHV, v); return v }
function addexpr(p) { if (substr(p, length(p) - length(PHE) + 1) == PHE) return p; return p PHE }
function build_path(s, e,   k, t, v, lv2, j, inner, d2) {
  BP = ""; BPDIR = 0; BPBARE = ""; BPN = 0
  if (s <= e && TK[s] == "P" && TV[s] == "(" && TK[e] == "P" && TV[e] == ")" && group_end(s, e) == e) { s++; e-- }
  k = s
  while (k <= e) {
    t = TK[k]; v = TV[k]
    if (t == "S") { BP = BP interp(v, TQ[k]); BPN++; k++; continue }
    if (t == "P" && (v == "." || v == "(" || v == ")")) { k++; continue }
    if (t == "W") {
      lv2 = tolower(v)
      if (lv2 == "__dir__") { if (BP == "") BPDIR = 1; BPN++; k++; continue }
      if (lv2 == "dirname" && k < e && TK[k + 1] == "P" && TV[k + 1] == "(") {
        j = group_end(k + 1, e); inner = ""
        for (d2 = k + 2; d2 < j; d2++) inner = inner tolower(TV[d2])
        if (inner == "__file__" && BP == "") { BPDIR = 1; BPN++; k = j + 1; continue }
        if (inner == "__dir__" && BP == "") { BPDIR = 1; BP = "/.."; BPN++; k = j + 1; continue }
      }
    }
    BP = addexpr(BP); BPN++
    if (k < e && TK[k + 1] == "P" && (TV[k + 1] == "(" || TV[k + 1] == "[")) { k = group_end(k + 1, e) + 1; BPN++ }
    else { if (t == "V") BPBARE = v; k++ }
  }
  if (BPN != 1) BPBARE = ""
}
function addcall(list, w) { if (index("," list ",", "," w ",") != 0) return list; return (list == "" ? "" : list ",") w }
function analyze(a, b,   k, w, lk, lv, isc, nm, hasa, d, j, txt, other, kwk, fe, mstr, supa, calls, c2) {
  while (a <= b && TK[a] == "P" && (TV[a] == "@" || TV[a] == "&")) a++
  if (b < a) return
  NS++; SL[NS] = TL[a]; SO[NS] = 0; ST[NS] = "txt"; SX[NS] = ""
  lk = TK[a]; lv = tolower(TV[a]); kwk = 0; fe = 0; mstr = 0; supa = 0; calls = ""
  for (k = a; k <= b; k++) {
    if (TK[k] == "W") {
      w = tolower(TV[k]); isc = (k < b && TK[k + 1] == "P" && TV[k + 1] == "(")
      if ((w in REQW) && !kwk) kwk = k
      if (isc) {
        if (w in INI) { F_ENV = 1; if (k != a) calls = addcall(calls, w) }
        else if (w in HDR) { F_HDR = 1; if (k != a) calls = addcall(calls, w) }
        else if (w == "define") { F_DEF = 1; if (k != a) calls = addcall(calls, w) }
        if (w == "file_exists" || w == "is_file" || w == "is_readable") fe = 1
      }
    } else if (TK[k] == "S" || TK[k] == "V") {
      if (index(tolower(TV[k]), "maintenance") != 0) mstr = 1
    }
    if (TK[k] == "V" && (TV[k] == "$_SERVER" || TV[k] == "$_ENV")) {
      j = k + 1
      while (j <= b && TK[j] == "P" && TV[j] == "[") j = group_end(j, b) + 1
      if (j <= b && TK[j] == "P" && TV[j] == "=") { F_ENV = 1; supa = 1 }
    }
  }
  if (kwk) {
    NR_Q++; build_path(kwk + 1, b)
    RQK[NR_Q] = tolower(TV[kwk]); RQP[NR_Q] = BP; RQD[NR_Q] = BPDIR; RQB[NR_Q] = BPBARE
    ST[NS] = "req"; SRQ[NS] = NR_Q
    if (kwk != a) SO[NS] = 1
    return
  }
  if (lv == "if" && lk == "W" && mstr && fe) {
    ST[NS] = "maint"; SX[NS] = "if (file_exists(...maintenance...))"; HAS_MAINT = 1
    for (k = a; k <= b; k++) {
      if (TK[k] == "V" && (index(tolower(TV[k]), "maintenance") != 0 || (k < b && TK[k + 1] == "P" && TV[k + 1] == "="))) MV[TV[k]] = 1
    }
    return
  }
  txt = ""; other = 1
  if (lk == "W") {
    isc = (a < b && TK[a + 1] == "P" && TV[a + 1] == "(")
    if (lv in NEU) { txt = lv; other = 0 }
    else if (lv == "const") { txt = lv; F_DEF = 1; other = 0 }
    else if (isc && ((lv in INI) || (lv in HDR) || lv == "define")) { txt = rd_id(lv); other = 0 }
    else if (lv in CTL) { txt = lv " (...)" }
    else if (isc) { txt = rd_id(lv) "(...)" }
    else txt = rd_id(lv)
  } else if (lk == "V") {
    nm = TV[a]; hasa = 0; d = 0
    for (k = a; k <= b; k++) {
      if (TK[k] == "P") {
        if (TV[k] == "(" || TV[k] == "[") d++
        else if (TV[k] == ")" || TV[k] == "]") d--
        else if (TV[k] == "=" && d == 0) hasa = 1
      }
    }
    if ((nm == "$_SERVER" || nm == "$_ENV") && supa) { txt = nm "[...] assignment"; other = 0 }
    else if (nm in SG) txt = nm "[...] " (hasa ? "assignment" : "expression")
    else txt = "$var " (hasa ? "assignment" : "expression")
  } else if (lk == "H") txt = "inline-html"
  else if (lk == "X") txt = "heredoc-or-nowdoc (the rest of the file is not analysed)"
  else if (lk == "B") txt = "backtick-shell"
  else txt = "(expression)"
  if (calls != "") txt = txt " [calls: " calls "]"
  SX[NS] = txt; SO[NS] = other
}
function resolve(p, isdir) {
  if (substr(p, 1, 1) == "/" && !isdir) return collapse(p)
  return collapse(DOCROOT "/" p)
}
END {
  lex()
  s0 = 1; dp = 0
  for (k = 1; k <= NT; k++) {
    if (TK[k] == "P") {
      v = TV[k]
      if (v == "(" || v == "[") { dp++; continue }
      if (v == ")" || v == "]") { if (0 < dp) dp--; continue }
      if (dp == 0 && (v == ";" || v == "{" || v == "}" || v == "?" GT)) { analyze(s0, k - 1); s0 = k + 1 }
    }
  }
  if (s0 <= NT) analyze(s0, NT)
  PUBIDX = collapse(APP "/public/index.php")
  CANON = "/" AF "/public/index.php"
  for (r = 1; r <= NR_Q; r++) {
    p = RQP[r]; q = index(p, "?"); hq = 0
    if (0 < q) { p = substr(p, 1, q - 1); hq = 1 }
    q = index(p, "#"); if (0 < q) p = substr(p, 1, q - 1)
    dyn = (index(p, PHE) != 0 || index(p, PHV) != 0)
    kind = "other"; canon = 0
    if (tolower(p) ~ /maintenance(\.php)?$/) kind = "maint"
    else if (dyn) { if (RQB[r] != "" && (RQB[r] in MV)) kind = "maint" }
    else if (RQD[r] && substr(p, 1, 1) != "/") kind = "other"
    else if (resolve(p, RQD[r]) == PUBIDX) { kind = "pub"; if (RQD[r] && p == CANON) canon = 1 }
    if (hq) kind = "other"
    if (kind == "maint") HAS_MAINT = 1
    d = p
    if (HOME != "" && HOME != "/" && index(d, HOME "/") == 1) d = "~" substr(d, length(HOME) + 1)
    d = rd_path(d); if (hq) d = d "?" RD
    RQKIND[r] = kind; RQCANON[r] = canon; RQDISP[r] = d
    if (kind == "pub") NPUB++
    else if (kind == "other") { if (index("," OTH ",", "," d ",") == 0) OTH = (OTH == "" ? "" : OTH ",") d }
  }
  ne = 0
  for (s = 1; s <= NS; s++) {
    txt = ""
    if (ST[s] == "req") {
      r = SRQ[s]; kw = RQK[r]
      if (RQKIND[r] == "pub" && RQCANON[r] && !SO[s]) txt = ""
      else if (RQKIND[r] == "maint") txt = kw " maintenance-include" (index(RQP[r], PHE) == 0 && index(RQP[r], PHV) == 0 ? " path=" RQDISP[r] : "")
      else txt = kw " path=" RQDISP[r]
    } else txt = SX[s]
    if (txt != "") { ne++; EL[ne] = "L" SL[s] ": " clip(txt) }
    if (SO[s]) OTHER = 1
  }
  if (UNTERM || STOPPED || 65536 < SZ || 1 < NPUB) OTHER = 1
  fl = ""
  if (F_ENV) fl = fl (fl == "" ? "" : ",") "sets_ini_or_env"
  if (F_HDR) fl = fl (fl == "" ? "" : ",") "sends_headers"
  if (F_DEF) fl = fl (fl == "" ? "" : ",") "defines"
  if (OTH != "") fl = fl (fl == "" ? "" : ",") "requires_other"
  if (NPUB == 0) fl = fl (fl == "" ? "" : ",") "app_path_differs"
  if (OTHER) fl = fl (fl == "" ? "" : ",") "other_code"
  if (HAS_MAINT && PUBMAINT != "yes") fl = fl (fl == "" ? "" : ",") "maintenance_missing_in_public"
  print "E|lines=" NR " bytes=" SZ (65536 < SZ ? " (only the first 65536 bytes were analysed)" : "")
  lim = (ne <= 39 ? ne : 38)
  for (i = 1; i <= lim; i++) print "E|" EL[i]
  if (lim < ne) print "E|... " (ne - lim) " additional statement(s) not listed"
  print "F|requires_public_index|" (0 < NPUB ? "yes" : "no")
  print "F|requires_other|" (OTH == "" ? "none" : clip(OTH))
  print "F|sets_ini_or_env|" (F_ENV ? "yes" : "no")
  print "F|sends_headers|" (F_HDR ? "yes" : "no")
  print "F|has_maintenance_include|" (HAS_MAINT ? "yes" : "no")
  print "F|app_path_differs|" (NPUB == 0 ? "yes" : "no")
  print "F|defines|" (F_DEF ? "yes" : "no")
  print "F|other_code|" (OTHER ? "yes" : "no")
  if (HAS_MAINT) print "F|public_has_maintenance|" (PUBMAINT == "yes" ? "yes" : "no")
  print "V|" (fl == "" ? "SHIM-SAFE-TO-DROP" : "SHIM-DOES-SOMETHING(" fl ")")
}'
shim_report() { # $1 = shim file, $2 = its size, $3 = docroot, $4 = app dir, $5 = app folder name, $6 = app/public; prints E|, F| and V| lines
  local pm=no
  [ -f "$6/index.php" ] && grep -qi maintenance "$6/index.php" 2>/dev/null && pm=yes
  head -c 65536 "$1" 2>/dev/null | tr '\r\t' '  ' | tr -c '[:print:]\n' '?' | TO 20 env SH_DOCROOT="$3" SH_APP="$4" SH_AF="$5" SH_SIZE="$2" SH_PUBMAINT="$pm" awk "$SHIM_AWK" 2>/dev/null
}

# ---- verdict bookkeeping ----
RS_J=""; RS_L=""
reason() { RS_J="${RS_J:+$RS_J; }$1"; RS_L="${RS_L:+$RS_L$'\n'}$1"; }
N_READY=0; N_ATTN=0; N_UNK=0
unknown() { # $1 = reason
  out "verdict: UNKNOWN-LAYOUT($1)"; N_UNK=$((N_UNK + 1))
}

inventory_pair() {
  local pair="$1" host key docroot app="" layout=none af="" app_in=no pub="" cands n e name sn raw t ty tt in_public ex
  local ne=0 shown=0 sym_out="" sym_broken="" only="" di c exp sz ks cnt needle dneedle a_n d_n up="" u
  local hd_only nm tok res sv
  host="${pair%%:*}"; key="${pair##*:}"
  PAIR="$pair"; RS_J=""; RS_L=""
  docroot="$HOME/$host.$BASE"

  if [ ! -d "$docroot" ]; then
    out "docroot: $(disp "$docroot") (missing)"; out "app: none"
    unknown "no-docroot"; return 0
  fi

  # ---- where is the app? ----
  if [ -f "$docroot/$key/artisan" ]; then
    app="$docroot/$key"; layout=standard
  else
    cands="$(TO 30 find "$docroot" -maxdepth 2 -name artisan -type f 2>/dev/null | sort)"
    n="$(printf '%s\n' "$cands" | grep -c .)" || true
    if [ "${n:-0}" -eq 1 ]; then
      app="${cands%/artisan}"
      if [ "$app" = "$docroot" ]; then layout="docroot-is-app"; else layout="renamed-folder"; fi
    elif [ "${n:-0}" -gt 1 ]; then
      layout=ambiguous
    elif [ -f "$HOME/$key/artisan" ]; then
      app="$HOME/$key"; layout=outside-docroot
    fi
  fi
  [ -n "$app" ] && af="${app##*/}"
  case "$layout" in standard|renamed-folder) app_in=yes ;; esac

  out "docroot: $(disp "$docroot")"
  case "$layout" in
    none|ambiguous) out "app: none" ;;
    docroot-is-app) out "app: docroot-is-app"; out "app_path: $(disp "$app")" ;;
    *) out "app: $(disp "$app")"; out "app_path: $(disp "$app")" ;;
  esac
  out "layout: $layout"
  if [ "$app_in" = yes ]; then out "app_parent_is_docroot: yes"; else out "app_parent_is_docroot: no"; fi
  [ -n "$app" ] && pub="$app/public"

  # ---- docroot top-level entries: name, type, symlink target, size. One level only; contents are never listed. ----
  for e in "$docroot"/* "$docroot"/.[!.]* "$docroot"/..?*; do
    present "$e" || continue
    name="${e##*/}"; ne=$((ne + 1))
    case "$name" in *[![:print:]]*) sn="$(printf '%s' "$name" | tr -c '[:print:]' '?')" ;; *) sn="$name" ;; esac
    if [ -L "$e" ]; then
      raw="$(readlink "$e" 2>/dev/null)"
      case "$raw" in "$docroot"/*) t="${raw#"$docroot"/}" ;; "$HOME"/*) t="$TL/${raw#"$HOME"/}" ;; *) t="$raw" ;; esac
      t="${t#./}"
      case "$t" in *[![:print:]]*) t="$(printf '%s' "$t" | tr -c '[:print:]' '?')" ;; esac
      if [ -e "$e" ]; then ex=yes; else ex=no; fi
      in_public=no
      if [ -n "$app" ] && [ "$layout" != docroot-is-app ]; then
        case "$raw" in
          *..*) ;;
          "$app"/public|"$app"/public/*) in_public=yes ;;
          *) tt="${raw#./}"; case "$tt" in "$docroot"/*) tt="${tt#"$docroot"/}" ;; esac
             if [ "$app_in" = yes ]; then case "$tt" in "$af"/public|"$af"/public/*) in_public=yes ;; esac; fi ;;
        esac
      fi
      if [ "$shown" -lt 200 ]; then out "entry: symlink $sn to=$t exists=$ex in_public=$in_public"; shown=$((shown + 1)); fi
      ty="symlink"
    elif [ -d "$e" ]; then
      if [ "$shown" -lt 200 ]; then out "entry: dir $sn"; shown=$((shown + 1)); fi
      ty="dir"
    elif [ -f "$e" ]; then
      if [ "$shown" -lt 200 ]; then out "entry: file $sn size=$(fsize "$e")"; shown=$((shown + 1)); fi
      ty="file"
    else
      if [ "$shown" -lt 200 ]; then out "entry: other $sn"; shown=$((shown + 1)); fi
      ty="other"
    fi
    # classification for the verdict (only for a normal layout)
    if [ -n "$app" ] && [ "$layout" != docroot-is-app ]; then
      ignorable "$name" && continue
      [ "$app_in" = yes ] && [ "$name" = "$af" ] && continue
      if [ "$ty" = symlink ]; then
        if [ "$ex" = no ]; then sym_broken="${sym_broken:+$sym_broken,}$sn"
        elif [ "$in_public" = no ]; then sym_out="${sym_out:+$sym_out,}$sn"
        elif ! present "$pub/$name"; then only="${only:+$only,}$sn"
        fi
      else
        present "$pub/$name" || only="${only:+$only,}$sn"
      fi
    fi
  done
  out "docroot_entries: $ne"
  [ "$ne" -gt "$shown" ] && out "docroot_entries_not_listed: $((ne - shown))"

  if [ -z "$app" ]; then
    if [ "$layout" = ambiguous ]; then
      c="$(printf '%s\n' "$cands" | while IFS= read -r e; do printf '%s,' "${e#"$docroot"/}"; done)"
      unknown "multiple-artisan-files(${c%,})"
    else
      unknown "no-laravel-app-found"
    fi
    return 0
  fi

  # ---- app/public ----
  out "public_dir: $(ftype "$pub")"
  out "public_index_php: $(yn "$([ -f "$pub/index.php" ] && echo yes)")"
  out "public_htaccess: $(yn "$([ -f "$pub/.htaccess" ] && echo yes)")"
  out "public_build: $(ftype "$pub/build")"
  out "public_images: $(ftype "$pub/images")"
  out "public_robots_txt: $(ftype "$pub/robots.txt")"
  c=""; for t in favicon.ico favicon.svg favicon.png favicon-16x16.png favicon-32x32.png apple-touch-icon.png; do present "$pub/$t" && c="${c:+$c,}$t"; done
  out "public_favicons: ${c:-none}"
  if [ -L "$pub/storage" ]; then
    raw="$(readlink "$pub/storage" 2>/dev/null)"
    case "$raw" in "$app"/*) t="${raw#"$app"/}" ;; "$HOME"/*) t="$TL/${raw#"$HOME"/}" ;; *) t="$raw" ;; esac
    case "$t" in *[![:print:]]*) t="$(printf '%s' "$t" | tr -c '[:print:]' '?')" ;; esac
    if [ -e "$pub/storage" ]; then ex=yes; else ex=no; fi
    out "public_storage: symlink to=$t exists=$ex"
  else
    out "public_storage: $(ftype "$pub/storage")"
  fi
  for t in build images storage favicon.ico favicon.svg favicon.png apple-touch-icon.png robots.txt; do
    out "asset: $t docroot=$(ftype "$docroot/$t") public=$(ftype "$pub/$t")"
  done

  # ---- the docroot index.php shim: only the verdict is printed ----
  if [ "$layout" = docroot-is-app ]; then
    out "shim: not-applicable"
  elif [ -f "$docroot/index.php" ]; then
    di="$docroot/index.php"; sz="$(fsize "$di")"
    out "docroot_index_php: file size=$sz"
    exp=""; [ "$app_in" = yes ] && exp="<?php require __DIR__.'/$af/public/index.php';"
    c=""
    if [ -n "$exp" ] && [ "$sz" != "?" ] && [ "$sz" -le 4096 ]; then
      c="$(head -c 4096 "$di" 2>/dev/null | tr -d '\r' | tr -s '[:space:]' ' ')"; c="${c# }"; c="${c% }"
    fi
    if [ -n "$exp" ] && [ "$c" = "$exp" ]; then
      out "shim: standard"
    else
      out "shim: custom"
      res="$(shim_report "$di" "$sz" "$docroot" "$app" "$af" "$pub")"
      printf '%s\n' "$res" | while IFS= read -r e; do
        case "$e" in
          E\|*) out "shim_extra: ${e#E|}" ;;
          F\|*) nm="${e#F|}"; out "shim_${nm%%|*}: ${nm#*|}" ;;
        esac
      done
      sv="$(printf '%s\n' "$res" | grep '^V|' | head -1)"; sv="${sv#V|}"
      if [ -n "$sv" ]; then out "shim_verdict: $sv"; reason "custom-shim($sv)"; else reason "custom-shim"; fi
    fi
  else
    out "docroot_index_php: missing"; out "shim: missing"
    [ "$layout" != docroot-is-app ] && reason "docroot-index.php-missing"
  fi

  # ---- .htaccess: docroot vs public ----
  ht_scan "$pub/.htaccess"
  local P_L="$HT_L" P_HTTPS="$HT_HTTPS" P_AUTH="$HT_AUTH"
  print_ht public "$pub/.htaccess"
  ht_scan "$docroot/.htaccess"
  print_ht docroot "$docroot/.htaccess"
  hd_only=""
  if [ -f "$docroot/.htaccess" ] && [ "$layout" != docroot-is-app ]; then
    for tok in $HT_L; do case " $P_L " in *" $tok "*) ;; *) hd_only="${hd_only:+$hd_only,}$tok" ;; esac; done
    if [ -n "$hd_only" ]; then out "htaccess_diff_signal: docroot-only $hd_only"; else out "htaccess_diff_signal: none"; fi
    [ -n "$hd_only" ] && reason "docroot-htaccess-directives-not-in-public($hd_only)"
    [ "$HT_HTTPS" = yes ] && [ "$P_HTTPS" = no ] && reason "docroot-https-redirect-not-in-public"
    [ "$HT_AUTH" = yes ] && [ "$P_AUTH" = no ] && reason "docroot-auth-header-passthrough-not-in-public"
  else
    out "htaccess_diff_signal: n/a"
  fi

  # ---- .user.ini / php.ini (names only) ----
  a_n=""; d_n=""; nm=""
  for t in .user.ini php.ini; do
    present "$docroot/$t" && d_n="${d_n:+$d_n,}$t"
    present "$pub/$t" && a_n="${a_n:+$a_n,}$t"
    if present "$docroot/$t" && ! present "$pub/$t"; then nm="${nm:+$nm,}$t"; fi
  done
  out "php_ini: docroot=${d_n:-none} public=${a_n:-none}"
  [ -n "$nm" ] && [ "$layout" != docroot-is-app ] && reason "docroot-php-ini-not-in-public($nm)"

  # ---- cron: counts only, never lines ----
  cron_load
  out "cron_status: $CRON_STATE"
  if [ "$CRON_STATE" = read ]; then
    case "$app" in "$HOME"/*) needle="${app#"$HOME"/}" ;; *) needle="$app" ;; esac
    dneedle="$host.$BASE"
    cnt="$(count_lines "$CRON_TEXT" "$needle")"; out "cron_lines_mentioning_app: $cnt"
    cnt="$(count_lines "$CRON_TEXT" "$dneedle" "$needle")"; out "cron_lines_mentioning_docroot_only: $cnt"
    [ "$cnt" -gt 0 ] && [ "$layout" != docroot-is-app ] && reason "cron-mentions-docroot-outside-app($cnt lines)"
  else
    out "cron_lines_mentioning_app: unknown"; out "cron_lines_mentioning_docroot_only: unknown"
  fi

  # ---- disk and uploads: counts only ----
  if [ -d "$pub" ]; then
    ks="$(TO 60 du -sk "$pub" 2>/dev/null | cut -f1)"; out "public_size_kb: ${ks:-unknown}"
  else
    out "public_size_kb: n/a"
  fi
  if [ -d "$pub/storage" ]; then
    cnt=$(( $(TO 60 find -H "$pub/storage" -type f 2>/dev/null | wc -l) )); out "public_storage_files: $cnt"
  else
    out "public_storage_files: n/a"
  fi
  for u in storage uploads; do [ -L "$docroot/$u" ] && up="${up:+$up,}docroot/$u"; done
  [ -L "$pub/storage" ] && up="${up:+$up,}public/storage"
  if [ -n "$up" ]; then out "uploads_via_symlink: yes ($up)"; else out "uploads_via_symlink: no"; fi
  if { [ -L "$docroot/storage" ] || [ -L "$docroot/uploads" ]; } && ! present "$pub/storage" && [ "$layout" != docroot-is-app ]; then
    reason "public/storage-missing-while-uploads-symlink-exists"
  fi
  [ "$(ftype "$pub/storage")" = broken-symlink ] && reason "public/storage-symlink-broken"

  # ---- the rest of the verdict ----
  [ -d "$pub" ] || reason "public-dir-missing"
  if [ -d "$pub" ]; then
    [ -f "$pub/index.php" ] || reason "public/index.php-missing"
    [ -f "$pub/.htaccess" ] || reason "public/.htaccess-missing"
  fi
  [ "$layout" = outside-docroot ] && reason "app-not-inside-docroot"
  [ "$layout" = renamed-folder ] && reason "app-folder-is-$af-not-$key"
  [ -n "$sym_broken" ] && reason "docroot-symlink-broken($(trunc "$sym_broken"))"
  [ -n "$sym_out" ] && reason "docroot-symlink-outside-public($(trunc "$sym_out"))"
  [ -n "$only" ] && reason "docroot-only-entries($(trunc "$only"))"

  if [ "$layout" = docroot-is-app ]; then
    out "reason: docroot-is-app (the docroot itself holds artisan, vendor and the environment file; the move would be docroot to docroot/public, not the standard layout)"
    unknown "docroot-is-app"; return 0
  fi
  if [ -z "$RS_J" ]; then
    out "verdict: READY"; N_READY=$((N_READY + 1))
  else
    printf '%s\n' "$RS_L" | while IFS= read -r e; do out "reason: $e"; done
    out "verdict: NEEDS-ATTENTION($RS_J)"; N_ATTN=$((N_ATTN + 1))
  fi
}

echo "inventory: read-only, $# pair(s)"
for p in "$@"; do inventory_pair "$p"; done
echo "summary: READY=$N_READY NEEDS-ATTENTION=$N_ATTN UNKNOWN-LAYOUT=$N_UNK pairs=$#"
exit 0
