#!/usr/bin/env bash
# Fake-host test of tools/access-log-window-host.sh: synthetic logs in a temp HOME. Proves the window and site filters,
# that status/bytes/category are printed, that no address, path or user-agent text reaches the output, and that nothing is written.
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"; TOOL="$ROOT/tools/access-log-window-host.sh"
W="$(mktemp -d)"; trap 'rm -rf "$W"' EXIT
export HOME="$W/home"; mkdir -p "$HOME/access-logs"
FAIL=0; N=0
ok() { N=$((N + 1)); echo "ok   - $1"; }
bad() { N=$((N + 1)); echo "FAIL - $1"; FAIL=1; }
check() { if eval "$2"; then ok "$1"; else bad "$1"; printf '%s\n' "$OUT" | head -n 12 | sed 's/^/       | /'; fi; }
L() { printf '%s - - [28/Oct/2026:%s +0200] "GET %s HTTP/1.1" %s %s "-" "%s"\n' "$1" "$2" "$3" "$4" "$5" "$6"; }
{
  L 203.0.113.7 19:28:23 /dopemine/.env 200 0 'curl/8.5.0'
  L 203.0.113.7 19:28:24 /dopemine/database/database.sqlite 200 0 'curl/8.5.0'
  L 203.0.113.7 19:28:25 /dopemine/artisan 403 1242 'Mozilla/5.0 secretagent'
  L 203.0.113.7 19:40:00 /dopemine/.env 403 1242 'curl/8.5.0'
  L 203.0.113.7 19:28:30 / 200 5000 'curl/8.5.0'
} > "$HOME/access-logs/dopemine.example.test"
L 198.51.100.9 19:28:23 /mines/.env 200 900 'curl/8.5.0' > "$HOME/access-logs/mines.example.test"
# shellcheck disable=SC2034  # read inside eval by check
before="$(cd "$HOME" && find . -type f -exec cksum {} + | sort | cksum)"
OUT="$(bash "$TOOL" dopemine 2026-10-28 19:28:00 19:29:00 2>&1)"
check "lists the three in-window sensitive requests" '[ "$(printf "%s\n" "$OUT" | grep -c "status=")" = 3 ]'
check "prints status, bytes and category" 'printf "%s\n" "$OUT" | grep -q "19:28:23  status=200 bytes=0  env  ua=curl"'
check "other site and out-of-window rows are absent" '! printf "%s\n" "$OUT" | grep -q "bytes=900" && ! printf "%s\n" "$OUT" | grep -q "19:40"'
check "no address, path or agent text" '! printf "%s\n" "$OUT" | grep -qE "203\.0\.113|198\.51|/dopemine|secretagent"'
check "bad input is refused" '! bash "$TOOL" "../x" 2026-10-28 19:28:00 19:29:00 >/dev/null 2>&1'
check "writes nothing" '[ "$before" = "$(cd "$HOME" && find . -type f -exec cksum {} + | sort | cksum)" ]'
echo "$N checks"; exit $FAIL
