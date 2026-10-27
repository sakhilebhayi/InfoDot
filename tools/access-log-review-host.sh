#!/usr/bin/env bash
# Read-only review of the raw web-server access logs on the shared cPanel host: for each site, how many requests were
# ANSWERED (HTTP 200 or 206 with a body) for paths that must never be served, and on which days. This is the "check the
# raw access logs" owner action from docs/programme/SECURITY_ASSESSMENT.md S-2c / S-2d / S-2f.
#
#   access-log-review-host.sh [--since YYYY-MM-DD] [--times]
#
# It runs ON the host (piped over SSH by .github/workflows/access-log-review.yml, the same way the docroot tools are) and
# writes nothing: no file, no mode, no link. Its output is meant to be pasted into a PUBLIC repository's job log, so it
# prints COUNTS and DAYS only: never an IP address, never a request line, never a user agent string (only a family
# name such as curl or browser-like). A person who needs the individual lines runs the grep in the doc, privately.
#
# What counts as "must never be served" (category names are what is printed):
#   env  git  database  laravel_log  composer  vendor_autoload  artisan  phpunit  archive
# Counted: status 200 or 206 with more than 0 bytes ("answered"). Status 403 on the same paths is counted apart
# ("refused": evidence that someone asked while the guard held). The deploy probe and the smoke checks are curl clients
# that also ask for these paths, so the user-agent family is printed: a burst of curl at deploy time is our own check.
set -u -o pipefail

SINCE=""; TIMES=0
while [ $# -gt 0 ]; do
  case "$1" in
    --since) SINCE="${2:-}"; shift 2 ;;
    --times) TIMES=1; shift ;;
    -h|--help) sed -n '2,19p' "$0" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) echo "usage: $0 [--since YYYY-MM-DD] [--times]" >&2; exit 2 ;;
  esac
done
if [ -n "$SINCE" ]; then printf '%s\n' "$SINCE" | grep -Eq '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' || { echo "--since must look like 2026-10-20" >&2; exit 2; }; fi

files=()
for d in "$HOME/access-logs" "$HOME/logs"; do
  [ -d "$d" ] || continue
  for f in "$d"/*; do
    [ -f "$f" ] || continue
    case "$f" in *.ftp|*.ftp.*|*bytes_log*|*offsetftp*|*.tmp) continue ;; esac
    files+=("$f")
  done
done
if [ "${#files[@]}" -eq 0 ]; then
  echo "no raw access logs found under ~/access-logs or ~/logs: nothing to review (cPanel keeps them there when 'archive logs' is on)"
  exit 0
fi

# Sensitive-path prefilter: keeps the awk stage small on large logs. Case-insensitive, matched anywhere on the line.
PRE='%2e|\.env|\.git|\.sqlite|\.sql|\.db[ ?"]|storage/logs|laravel\.log|composer\.(json|lock)|vendor/autoload|/artisan|phpunit|\.(zip|tar|tgz|gz|7z|rar|bak)[ ?"]'

label_of() { # file name -> site label: drop directory, rotation suffix and the ssl_log marker
  basename "$1" | sed -E 's/\.gz$//; s/-(Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)-[0-9]{4}$//; s/[-_]ssl_log$//; s/[-_]ssl$//; s/_log$//'
}

records() {
  for f in "${files[@]}"; do
    lab="$(label_of "$f")"
    case "$f" in *.gz) gzip -dc -- "$f" 2>/dev/null ;; *) cat -- "$f" 2>/dev/null ;; esac \
      | sed -n '1p;$p' | grep -aoE '\[[0-9]{2}/[A-Za-z]{3}/[0-9]{4}' | sed -E 's/^\[//; s/^/__cover__\t/' | head -n 2
    case "$f" in *.gz) gzip -dc -- "$f" 2>/dev/null ;; *) cat -- "$f" 2>/dev/null ;; esac \
      | grep -aiE "$PRE" \
      | awk -v lab="$lab" -v since="$SINCE" '
        function fam(u) {
          u = tolower(u)
          if (u == "" || u == "-") return "none"
          if (u ~ /curl\//) return "curl"
          if (u ~ /wget/) return "wget"
          if (u ~ /python|requests|aiohttp|urllib|httpx/) return "python"
          if (u ~ /go-http|golang/) return "go"
          if (u ~ /bot|spider|crawl|scan/) return "bot-or-scanner"
          if (u ~ /mozilla/) return "browser-like"
          return "other"
        }
        {
          n = split($0, q, "\"")
          if (n < 3) next
          req = q[2]; ua = (n >= 6 ? q[6] : "")
          split(q[3], st, " "); status = st[1] + 0; bytes = st[2] + 0
          if (status != 200 && status != 206 && status != 403) next
          if (!match($0, /\[[0-9][0-9]\/[A-Za-z][A-Za-z][A-Za-z]\/[0-9][0-9][0-9][0-9]:/)) next
          dstr = substr($0, RSTART + 1, 11); tm = substr($0, RSTART + 13, 5)
          split(dstr, d, "/")
          mon = (index("JanFebMarAprMayJunJulAugSepOctNovDec", d[2]) + 2) / 3
          iso = sprintf("%04d-%02d-%02d", d[3], mon, d[1])
          if (since != "" && iso < since) next
          split(req, r, " ")
          p = tolower(r[2]); sub(/\?.*/, "", p); gsub(/%2e/, ".", p); gsub(/%2f/, "/", p)
          c = ""
          if (p ~ /\/\.env[a-z0-9._-]*$/ || p ~ /^\/\.env/) c = "env"
          else if (p ~ /\/\.git(\/|$)/) c = "git"
          else if (p ~ /\.(sqlite3?|sql)$/ || p ~ /\/database\/[^\/]*\.db$/) c = "database"
          else if (p ~ /\/storage\/logs\// || p ~ /laravel\.log$/) c = "laravel_log"
          else if (p ~ /\/composer\.(json|lock)$/) c = "composer"
          else if (p ~ /\/vendor\/autoload\.php$/) c = "vendor_autoload"
          else if (p ~ /\/artisan$/) c = "artisan"
          else if (p ~ /\/phpunit\.xml/) c = "phpunit"
          else if (p ~ /\.(zip|tar|tgz|7z|rar|bak)$/ || p ~ /\.tar\.gz$/) c = "archive"
          if (c == "") next
          kind = (status == 403) ? "refused" : ((bytes > 0) ? "answered" : "")
          if (kind == "") next
          print lab "\t" c "\t" kind "\t" iso "\t" $1 "\t" fam(ua) "\t" bytes "\t" tm
        }'
  done
}

records | awk -F'\t' -v nfiles="${#files[@]}" -v times="$TIMES" '
  function iso_of(d,   x, m) { split(d, x, "/"); m = (index("JanFebMarAprMayJunJulAugSepOctNovDec", x[2]) + 2) / 3; return sprintf("%04d-%02d-%02d", x[3], m, x[1]) }
  $1 == "__cover__" { c = iso_of($2); if (cmin == "" || c < cmin) cmin = c; if (c > cmax) cmax = c; next }
  {
    k = $1 SUBSEP $2
    if ($3 == "answered") {
      ans[k]++; day[k SUBSEP $4]++
      if (!((k SUBSEP $5) in ip)) { ip[k SUBSEP $5] = 1; nip[k]++ }
      if (!((k SUBSEP $6) in uaseen)) { uaseen[k SUBSEP $6] = 1; ualist[k] = ualist[k] (ualist[k] == "" ? "" : ",") $6 }
      uac[k SUBSEP $6]++
      if (!(k in bmin) || $7 + 0 < bmin[k]) bmin[k] = $7 + 0
      if ($7 + 0 > bmax[k]) bmax[k] = $7 + 0
      tl[k] = tl[k] (tl[k] == "" ? "" : " ") $4 "T" $8
      if (first[k] == "" || $4 < first[k]) first[k] = $4
      if ($4 > last[k]) last[k] = $4
      keys[k] = 1
    } else { ref[$1]++ }
  }
  END {
    print "access log review (counts only; answered = HTTP 200/206 with a body, refused = HTTP 403)"
    print "log files read: " nfiles "; the files cover " (cmin == "" ? "unknown" : cmin) " to " (cmax == "" ? "unknown" : cmax)
    print ""
    m = 0; for (k in keys) { split(k, a, SUBSEP); order[++m] = a[1] "\t" a[2]; line[a[1] "\t" a[2]] = k }
    for (i = 2; i <= m; i++) { v = order[i]; j = i - 1; while (j >= 1 && order[j] > v) { order[j + 1] = order[j]; j-- } order[j + 1] = v }
    if (m == 0) {
      print "ANSWERED: none. No request for a sensitive path was ever answered with a body in the logs read."
    } else {
      print "ANSWERED requests (200/206 with a body), by site and category:"
      printf "%-30s %-16s %8s %-13s %-10s %-10s %7s  %s\n", "site", "category", "answered", "bytes", "first day", "last day", "clients", "user agents (hits)"
      for (i = 1; i <= m; i++) {
        split(order[i], b, "\t"); k = line[order[i]]
        n = split(ualist[k], u, ","); s = ""
        for (t = 1; t <= n; t++) if (u[t] != "") s = s (s == "" ? "" : " ") u[t] "=" uac[k SUBSEP u[t]]
        by = (bmin[k] == bmax[k]) ? bmin[k] "" : bmin[k] ".." bmax[k]
        printf "%-30s %-16s %8d %-13s %-10s %-10s %7d  %s\n", b[1], b[2], ans[k], by, first[k], last[k], nip[k], s
        if (times) print "    at: " tl[k]
      }
    }
    print ""
    r = 0; for (x in ref) { rs[++r] = x }
    for (i = 2; i <= r; i++) { v = rs[i]; j = i - 1; while (j >= 1 && rs[j] > v) { rs[j + 1] = rs[j]; j-- } rs[j + 1] = v }
    print "REFUSED (403) requests for those paths, per site (the guard held):"
    for (i = 1; i <= r; i++) printf "  %-32s %d\n", rs[i], ref[rs[i]]
    if (r == 0) print "  (none)"
  }
'
