#!/usr/bin/env bash
# Fake-host test of tools/access-log-review-host.sh (the very script the workflow pipes to the host). No network, no
# SSH: HOME is a temp dir holding synthetic raw logs, plain and gzipped. Proves the counts, the day filter, that the
# review writes nothing, and that no IP address and no user-agent text can reach the output.
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TOOL="$ROOT/tools/access-log-review-host.sh"
W="$(mktemp -d)"; trap 'rm -rf "$W"' EXIT
export HOME="$W/home"; mkdir -p "$HOME/access-logs" "$HOME/logs"
FAIL=0; N=0
ok() { N=$((N + 1)); echo "ok   - $1"; }
bad() { N=$((N + 1)); echo "FAIL - $1"; FAIL=1; }
check() { if eval "$2"; then ok "$1"; else bad "$1"; printf '%s\n' "$OUT" | head -n 14 | sed 's/^/       | /'; fi; }
tree_sum() { (cd "$HOME" && find . -type f -exec cksum {} + | sort | cksum; find . -type d | sort | cksum); }

L() { # ip day mon year method path status bytes ua
  printf '%s - - [%s/%s/%s:12:00:00 +0000] "%s %s HTTP/1.1" %s %s "-" "%s"\n' "$1" "$2" "$3" "$4" "$5" "$6" "$7" "$8" "$9"
}
CURL='curl/8.5.0'; FF='Mozilla/5.0 (X11; Linux x86_64) Gecko/20100101 Firefox/131.0'; SCAN='Mozilla/5.0 zgrab/0.x scanner'
{
  L 203.0.113.7  20 Oct 2026 GET /dopemine/database/database.sqlite 200 21000000 "$CURL"
  L 203.0.113.7  20 Oct 2026 GET /dopemine/database/database.sqlite 200 21000000 "$CURL"
  L 203.0.113.7  20 Oct 2026 GET /dopemine/storage/logs/laravel.log 200 700000 "$CURL"
  L 198.51.100.9 21 Oct 2026 GET /dopemine/.env 403 500 "$FF"
  L 198.51.100.9 21 Oct 2026 GET /dopemine/composer.json 403 500 "$FF"
  L 192.0.2.44   22 Oct 2026 GET /dopemine/vendor/autoload.php 200 800 "$SCAN"
  L 192.0.2.44   22 Oct 2026 GET /dopemine/%2eenv 200 1200 "$SCAN"
  L 192.0.2.45   22 Oct 2026 GET /dopemine/artisan 200 0 "$FF"
  L 192.0.2.45   22 Oct 2026 GET /dopemine/artisan 304 0 "$FF"
  L 192.0.2.46   22 Oct 2026 GET /login 200 5000 "$FF"
  L 192.0.2.46   22 Oct 2026 GET /build/assets/app.css 200 9000 "$FF"
} > "$HOME/access-logs/dopemine.infodot.co.za-ssl_log"
{
  L 203.0.113.50 20 Oct 2026 GET /Dot.Tasks-main.zip 200 4000000 "$FF"
  L 203.0.113.51 21 Oct 2026 GET /Dot.Tasks-main.zip 200 4000000 "$FF"
  L 203.0.113.51 21 Oct 2026 GET /backups/site.tar.gz 200 900000 "$FF"
  L 203.0.113.52 21 Oct 2026 GET /index.php?file=.env 200 100 "$FF"
  L 203.0.113.53 03 Sep 2026 GET /tasks/.git/config 200 300 "$SCAN"
} | gzip -c > "$HOME/logs/tasks.infodot.co.za-Oct-2026.gz"

BEFORE="$(tree_sum)"
OUT="$(bash "$TOOL" 2>&1)"; RC=$?
check "runs and exits 0" "[ $RC -eq 0 ]"
check "reads both log files and states what dates they cover" "printf '%s' \"\$OUT\" | grep -q 'log files read: 2; the files cover 2026-09-03 to 2026-10-22'"
check "database answered twice, byte size shown, one client, curl x2" "printf '%s\n' \"\$OUT\" | grep -E '^dopemine.infodot.co.za +database +2 +21000000 +2026-10-20 +2026-10-20 +1 +curl=2'"
check "laravel_log answered once with its size" "printf '%s\n' \"\$OUT\" | grep -qE '^dopemine.infodot.co.za +laravel_log +1 +700000 '"
check "the percent-encoded .env was decoded and counted as answered by the scanner" "printf '%s\n' \"\$OUT\" | grep -E '^dopemine.infodot.co.za +env +1 +1200 ' | grep -q 'bot-or-scanner=1'"
check "403s are not in the answered table (env/composer refused only count under REFUSED)" "! printf '%s\n' \"\$OUT\" | grep -qE '^dopemine.infodot.co.za +composer' && printf '%s\n' \"\$OUT\" | grep -qE '^ +dopemine.infodot.co.za +2\$'"
check "a zero-byte answer and a 304 are not counted" "! printf '%s\n' \"\$OUT\" | grep -qE '^dopemine.infodot.co.za +artisan'"
check "ordinary pages and assets are never listed" "! printf '%s\n' \"\$OUT\" | grep -qE 'login|app.css'"
check "vendor_autoload answered once by the scanner" "printf '%s\n' \"\$OUT\" | grep -E '^dopemine.infodot.co.za +vendor_autoload +1 ' | grep -q 'bot-or-scanner=1'"
check "gzipped log: the archives downloaded 3 times by 2 browsers, with the size range" "printf '%s\n' \"\$OUT\" | grep -E '^tasks.infodot.co.za +archive +3 +900000..4000000 +2026-10-20 +2026-10-21 +2 +browser-like=3'"
check "a .env passed as a query string is not a request for the file (not counted)" "! printf '%s\n' \"\$OUT\" | grep -qE '^tasks.infodot.co.za +env'"
check "git config hit on 3 Sep is listed" "printf '%s\n' \"\$OUT\" | grep -qE '^tasks.infodot.co.za +git +1 +300 +2026-09-03'"
check "no time of day without --times" "! printf '%s\n' \"\$OUT\" | grep -q '    at:'"
# shellcheck disable=SC2034  # read inside the eval strings of check()
OUT_T="$(bash "$TOOL" --times 2>&1)"
check "--times adds the day and minute of each answered request, still no IP" "printf '%s\n' \"\$OUT_T\" | grep -q '    at: 2026-10-20T12:00 2026-10-20T12:00' && ! printf '%s' \"\$OUT_T\" | grep -qE '[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}'"

# privacy: nothing identifying reaches the output
check "no IP address anywhere in the output" "! printf '%s' \"\$OUT\" | grep -qE '[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}\\.[0-9]{1,3}'"
check "no user-agent text or request line in the output" "! printf '%s' \"\$OUT\" | grep -qiE 'Mozilla|Gecko|Firefox|zgrab|HTTP/1\\.1|GET /'"

# filter
OUT="$(bash "$TOOL" --since 2026-10-21 2>&1)"
check "--since drops the earlier days" "! printf '%s\n' \"\$OUT\" | grep -qE '^dopemine.infodot.co.za +database' && ! printf '%s\n' \"\$OUT\" | grep -qE '^tasks.infodot.co.za +git'"
check "--since keeps the later ones" "printf '%s\n' \"\$OUT\" | grep -qE '^tasks.infodot.co.za +archive +2 '"

# bad input
bash "$TOOL" --since yesterday >/dev/null 2>&1; check "a bad date is rejected (exit 2)" "[ \$? -eq 2 ]"
bash "$TOOL" --frobnicate >/dev/null 2>&1; check "an unknown option is rejected (exit 2)" "[ \$? -eq 2 ]"

# read-only
AFTER="$(tree_sum)"
check "the review changed nothing under HOME (files and folders checksum identical)" "[ \"$BEFORE\" = \"$AFTER\" ]"

# empty host
rm -rf "$HOME/access-logs" "$HOME/logs"
OUT="$(bash "$TOOL" 2>&1)"; RC=$?
check "no log folders: says so and exits 0" "[ $RC -eq 0 ] && printf '%s' \"\$OUT\" | grep -q 'no raw access logs found'"
mkdir -p "$HOME/access-logs"; L 192.0.2.1 01 Oct 2026 GET /login 200 100 "$FF" > "$HOME/access-logs/quiet.infodot.co.za"
OUT="$(bash "$TOOL" 2>&1)"
check "logs with no sensitive request: reports none and 'ANSWERED: none'" "printf '%s' \"\$OUT\" | grep -q 'ANSWERED: none'"

echo; echo "$N checks, $( [ "$FAIL" = 0 ] && echo 'all passed' || echo 'SOME FAILED' )"
exit "$FAIL"
