#!/usr/bin/env bash
# Read-only: for ONE site and ONE short time window, list every request for a path that must never be served, with its
# status, bytes and category, to settle whether a deploy-probe "200" was a real file answer or something else.
#
#   access-log-window-host.sh <site-label-prefix> <YYYY-MM-DD> <HH:MM:SS> <HH:MM:SS>
#
# Meant to run ON the host (piped over SSH by .github/workflows/access-log-window.yml). It writes nothing. Its output goes
# to a PUBLIC job log, so it prints only: second, status, bytes, category, user-agent family. Never an address, a path
# or a request line. Log times are the server's own clock (the files are written in server local time, with the offset).
set -u -o pipefail
SITE="${1:-}"; DAY="${2:-}"; FROM="${3:-}"; TO="${4:-}"
printf '%s' "$SITE" | grep -Eq '^[a-z][a-z0-9-]{1,31}$' || { echo "site must be a label prefix such as dopemine" >&2; exit 2; }
printf '%s' "$DAY" | grep -Eq '^[0-9]{4}-[0-9]{2}-[0-9]{2}$' || { echo "day must be YYYY-MM-DD" >&2; exit 2; }
for t in "$FROM" "$TO"; do printf '%s' "$t" | grep -Eq '^[0-9]{2}:[0-9]{2}:[0-9]{2}$' || { echo "times must be HH:MM:SS" >&2; exit 2; }; done
files=()
for d in "$HOME/access-logs" "$HOME/logs"; do
  [ -d "$d" ] || continue
  for f in "$d"/"$SITE".*; do
    [ -f "$f" ] || continue
    case "$f" in *.ftp|*.ftp.*|*bytes_log*|*offsetftp*|*.tmp) continue ;; esac
    files+=("$f")
  done
done
echo "window review for ${SITE}.* on $DAY $FROM to $TO (server clock); log files read: ${#files[@]}"
[ "${#files[@]}" -gt 0 ] || { echo "no log files for that site"; exit 0; }
PRE='%2e|\.env|\.git|\.sqlite|\.sql|storage/logs|laravel\.log|composer\.(json|lock)|vendor/autoload|/artisan|phpunit'
for f in "${files[@]}"; do
  case "$f" in *.gz) gzip -dc -- "$f" 2>/dev/null ;; *) cat -- "$f" 2>/dev/null ;; esac | grep -aiE "$PRE" | awk -v day="$DAY" -v from="$FROM" -v to="$TO" '
    {
      n = split($0, q, "\""); if (n < 3) next
      split(q[3], st, " "); status = st[1] + 0; bytes = st[2] + 0
      if (!match($0, /\[[0-9][0-9]\/[A-Za-z][A-Za-z][A-Za-z]\/[0-9][0-9][0-9][0-9]:/)) next
      dstr = substr($0, RSTART + 1, 11); tm = substr($0, RSTART + 13, 8)
      split(dstr, d, "/"); mon = (index("JanFebMarAprMayJunJulAugSepOctNovDec", d[2]) + 2) / 3
      iso = sprintf("%04d-%02d-%02d", d[3], mon, d[1])
      if (iso != day || tm < from || tm > to) next
      split(q[2], r, " "); p = tolower(r[2]); sub(/\?.*/, "", p)
      c = "other"
      if (p ~ /\.env/) c = "env"; else if (p ~ /\.git/) c = "git"; else if (p ~ /\.(sqlite3?|sql)$/) c = "database"
      else if (p ~ /laravel\.log$|storage\/logs/) c = "laravel_log"; else if (p ~ /composer\.(json|lock)$/) c = "composer"
      else if (p ~ /vendor\/autoload/) c = "vendor_autoload"; else if (p ~ /artisan$/) c = "artisan"; else if (p ~ /phpunit/) c = "phpunit"
      ua = (n >= 6 ? tolower(q[6]) : ""); f = (ua ~ /curl\//) ? "curl" : (ua ~ /mozilla/ ? "browser-like" : (ua == "" || ua == "-" ? "none" : "other"))
      printf "%s  status=%d bytes=%d  %s  ua=%s\n", tm, status, bytes, c, f
    }' | sort
done
echo "end of window review"
