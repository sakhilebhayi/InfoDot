#!/usr/bin/env bash
# Public probe (no SSH, no content read): does any app folder answer over HTTP? For each "host:folder" it requests
# a list of sensitive paths and prints only status codes. Exit 1 if any returns 200/301/302 (or a body that
# starts like the real file), 0 when all are blocked or absent. Also checks the site itself still answers.
#   tools/probe-exposure.sh dopemine:dopemine engage:engage ...      (host label : app folder under that host)
set -uo pipefail
BASE="${BASE_DOMAIN:-infodot.co.za}"
PATHS=(.env .env.bak-secrets artisan composer.json composer.lock database/database.sqlite storage/logs/laravel.log vendor/autoload.php phpunit.xml)
BAD=0
for spec in "$@"; do
  h="${spec%%:*}"; k="${spec##*:}"; url="https://$h.$BASE"
  line="$h: site / $(curl -s -o /dev/null -m 15 -w '%{http_code}' "$url/") /up $(curl -s -o /dev/null -m 15 -w '%{http_code}' "$url/up") |"
  for p in "${PATHS[@]}"; do
    c=$(curl -s -o /dev/null -m 15 -w '%{http_code}' "$url/$k/$p")
    case "$c" in 200|301|302) line="$line $p=$c(EXPOSED)"; BAD=1;; *) line="$line $p=$c";; esac
  done
  echo "$line"
done
[ "$BAD" = 0 ] && echo "No app folder is readable." || echo "EXPOSED: at least one app folder answers over HTTP."
exit "$BAD"
