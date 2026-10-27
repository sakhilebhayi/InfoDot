#!/usr/bin/env bash
# Public probe (no SSH, no content read): does any app folder answer over HTTP? For each "host:folder" it requests
# a list of sensitive paths and prints only status codes. Exit 1 if any returns 200 (a 301/302 is the app
# routing an unknown path to its login, not a served file), 0 when all are blocked or absent. Also checks the site itself still answers.
#   tools/probe-exposure.sh dopemine:dopemine engage:engage ...      (host label : app folder under that host)
#   tools/probe-exposure.sh apex:main                                 (the main domain: https://infodot.co.za/main/..., InfoDot's own app folder)
#
# Optional retries, to tell "exposed for a few seconds after a change" from "persistently exposed":
#   PROBE_RETRIES=3 PROBE_WAIT=5 tools/probe-exposure.sh ...         (defaults: 1 attempt, 5 s between attempts)
# With more than one attempt every attempt is printed ("attempt i/N t+Ns: ..."), so a brief window is visible, and a
# summary lists each path that ever answered 200 with its status per attempt, as
#   EXPOSED persistent: <host>:<folder> <path> attempts: 1=200 2=200 3=200   (200 on the last attempt too)
#   EXPOSED transient:  <host>:<folder> <path> attempts: 1=200 2=403 3=403   (200 earlier, clear on the last attempt)
# The exit status is 1 if ANY probe ever returned 200, whichever kind. With no env set the output is the single-pass
# output this script always had.
set -uo pipefail
BASE="${BASE_DOMAIN:-infodot.co.za}"
RETRIES="${PROBE_RETRIES:-1}"; WAIT="${PROBE_WAIT:-5}"
case "$RETRIES" in ''|*[!0-9]*) echo "PROBE_RETRIES must be a whole number" >&2; exit 2 ;; esac
case "$WAIT" in ''|*[!0-9]*) echo "PROBE_WAIT must be a whole number of seconds" >&2; exit 2 ;; esac
if [ "$RETRIES" -lt 1 ] || [ "$RETRIES" -gt 20 ]; then echo "PROBE_RETRIES must be 1 to 20" >&2; exit 2; fi
if [ "$WAIT" -gt 120 ]; then echo "PROBE_WAIT must be 0 to 120" >&2; exit 2; fi
PATHS=(.env .env.bak-secrets artisan composer.json composer.lock package.json database/database.sqlite storage/logs/laravel.log vendor/autoload.php phpunit.xml)
NP=${#PATHS[@]}
code() { curl -s -o /dev/null -m 15 -w '%{http_code}' "$1"; }

BAD=0
TL=()   # per (spec, path): status codes of every attempt, space separated "1=200 2=403"
START=$SECONDS
a=1
while [ "$a" -le "$RETRIES" ]; do
  s=0
  for spec in "$@"; do
    h="${spec%%:*}"; k="${spec##*:}"; url="https://$h.$BASE"; [ "$h" = apex ] && url="https://$BASE"
    prefix=""; [ "$RETRIES" -gt 1 ] && prefix="attempt $a/$RETRIES t+$((SECONDS - START))s: "
    line="${prefix}$h: site / $(code "$url/") /up $(code "$url/up") |"
    j=0
    for p in "${PATHS[@]}"; do
      c=$(code "$url/$k/$p")
      TL[s*NP+j]="${TL[s*NP+j]:-}${TL[s*NP+j]:+ }$a=$c"
      case "$c" in 200) line="$line $p=$c(EXPOSED)"; BAD=1;; *) line="$line $p=$c";; esac
      j=$((j + 1))
    done
    echo "$line"
    s=$((s + 1))
  done
  a=$((a + 1))
  [ "$a" -le "$RETRIES" ] && sleep "$WAIT"
done

PERSIST=0; TRANS=0
if [ "$RETRIES" -gt 1 ]; then
  s=0
  for spec in "$@"; do
    j=0
    for p in "${PATHS[@]}"; do
      t="${TL[s*NP+j]:-}"
      case " $t " in
        *"=200 "*) # ever 200
          case " $t" in
            *" $RETRIES=200") echo "EXPOSED persistent: $spec $p attempts: $t"; PERSIST=$((PERSIST + 1)) ;;
            *) echo "EXPOSED transient: $spec $p attempts: $t"; TRANS=$((TRANS + 1)) ;;
          esac ;;
      esac
      j=$((j + 1))
    done
    s=$((s + 1))
  done
fi
if [ "$BAD" = 0 ]; then
  echo "No app folder is readable."
elif [ "$RETRIES" -gt 1 ]; then
  echo "EXPOSED: at least one app folder answered 200 at some attempt ($PERSIST persistent, $TRANS transient)."
else
  echo "EXPOSED: at least one app folder answers over HTTP."
fi
exit "$BAD"
