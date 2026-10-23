#!/usr/bin/env bash
# Public status codes (nothing else is read) of /, /up and /login for each host label, one stable line per host:
#   <host>: / 200 /up 200 /login 302
# Redirects are not followed, so a 302 stays a 302. Used by the App Folder Guard docroot mode to record what a site
# answers before a change and compare after it.
#   tools/site-status.sh tasks pres sheets ...
set -uo pipefail
BASE="${BASE_DOMAIN:-infodot.co.za}"
code() { curl -s -o /dev/null -m 15 -w '%{http_code}' "$1"; }
for h in "$@"; do
  printf '%s' "$h" | grep -Eq '^[a-z0-9-]+$' || { echo "bad host label [$h]" >&2; exit 2; }
  url="https://$h.$BASE"
  echo "$h: / $(code "$url/") /up $(code "$url/up") /login $(code "$url/login")"
done
