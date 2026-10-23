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
# script and only the verdict (standard/custom) is printed; .htaccess files are read for DIRECTIVE NAMES from a fixed list
# of Apache directive names (anything else is counted as "unrecognised", never printed); the crontab is only counted.
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
  local hd_only nm tok
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
    if [ -n "$exp" ] && [ "$c" = "$exp" ]; then out "shim: standard"; else out "shim: custom"; reason "custom-shim"; fi
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
