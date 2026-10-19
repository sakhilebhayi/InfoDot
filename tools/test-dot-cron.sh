#!/usr/bin/env bash
# Fake-host test of tools/dot-cron-runner.sh and tools/dot-cron-host.sh. No network, no real crontab: HOME is a
# temp dir, `crontab` and `php` are shims. Runs in CI (Linux, real flock and GNU xargs) and locally.
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
W="$(mktemp -d)"; trap 'rm -rf "$W"' EXIT
export HOME="$W/home"; SHIM="$W/shim"; mkdir -p "$HOME/bin" "$SHIM" "$W/apps"
export PATH="$SHIM:$PATH" CRONTAB_FILE="$W/crontab.txt" RUN="$W/run"; mkdir -p "$RUN"
FAIL=0; ok() { echo "ok   - $1"; }; bad() { echo "FAIL - $1"; FAIL=1; }
check() { if eval "$2"; then ok "$1"; else bad "$1"; fi; }

cat > "$SHIM/crontab" <<'SH'
#!/bin/sh
if [ "$1" = "-l" ]; then [ -f "$CRONTAB_FILE" ] && cat "$CRONTAB_FILE" || { echo "no crontab for user" >&2; exit 1; }; else cp "$1" "$CRONTAB_FILE"; fi
SH
# php shim: logs start/end, tracks concurrency, behaviour chosen per app dir name (cwd): slow, fail, hang
cat > "$SHIM/php" <<'SH'
#!/bin/sh
app=$(basename "$PWD"); cmd="$2"
touch "$RUN/active.$$"; n=$(ls "$RUN" | grep -c '^active\.'); m=$(cat "$RUN/max" 2>/dev/null || echo 0); [ "$n" -gt "$m" ] && echo "$n" > "$RUN/max"
echo "$app $cmd" >> "$RUN/calls"
case "$app" in slow*) sleep 1;; hang*) sleep 5;; esac
rm -f "$RUN/active.$$"
case "$app" in fail*) exit 3;; esac
echo "No scheduled commands are ready to run."
SH
chmod +x "$SHIM/crontab" "$SHIM/php"
command -v flock >/dev/null || { printf '#!/bin/sh\nexit 0\n' > "$SHIM/flock"; chmod +x "$SHIM/flock"; echo "(no flock on this machine: shim used; CI runs the real one)"; }
cp "$ROOT/tools/dot-cron-runner.sh" "$HOME/bin/dot-cron-runner"; chmod +x "$HOME/bin/dot-cron-runner"
HOSTSH="$ROOT/tools/dot-cron-host.sh"

line() { echo "* * * * * cd $W/apps/$1 && exec $SHIM/php artisan $2 > storage/logs/cron-$3.log 2>&1 # dot-$1"; }
for a in tasks auction slow1 slow2 slow3 slow4 slow5 slow6 failer hang; do mkdir -p "$W/apps/$a/storage/logs"; done
{
  echo "*/5 * * * * /home/u/charts/analytics/run.sh # charts-analytics-keepalive"
  for a in tasks auction slow1 slow2 slow3 slow4 slow5 slow6 failer hang; do line $a schedule:run scheduler; done
  line tasks queue:work queue; line auction queue:work queue
} > "$CRONTAB_FILE"
OTHERS_BEFORE=$(grep -c 'charts-analytics-keepalive' "$CRONTAB_FILE")

# 1. migrate is refused before install
bash "$HOSTSH" migrate tasks >/dev/null 2>&1; check "migrate is refused while the runner line is not installed" "[ $? -ne 0 ]"
# 2. install
bash "$HOSTSH" install >/dev/null 2>&1; check "install adds exactly one runner line" "[ \$(grep -c '# dot-cron-runner' '$CRONTAB_FILE') = 1 ]"
check "install keeps the keepalive line" "[ \$(grep -c charts-analytics-keepalive '$CRONTAB_FILE') = $OTHERS_BEFORE ]"
check "install keeps a backup of the previous crontab" "ls '$HOME'/.dot-crontab.backup-* >/dev/null 2>&1"
bash "$HOSTSH" install >/dev/null 2>&1; check "install twice still leaves one runner line" "[ \$(grep -c '# dot-cron-runner' '$CRONTAB_FILE') = 1 ]"
# 3. migrate
bash "$HOSTSH" migrate tasks auction slow1 slow2 slow3 slow4 slow5 slow6 failer hang >/dev/null 2>&1
check "migrate left no dot-<app> lines for the moved apps" "! grep -q '# dot-tasks\|# dot-auction\|# dot-slow' '$CRONTAB_FILE'"
check "migrate keeps the keepalive and runner lines" "[ \$(grep -c 'charts-analytics-keepalive\|# dot-cron-runner' '$CRONTAB_FILE') = 2 ]"
check "tasks.cron holds its two commands without time fields or marker" "[ \$(grep -c . '$HOME/.dot-cron.d/tasks.cron') = 2 ] && ! grep -q '^\*\|# dot-' '$HOME/.dot-cron.d/tasks.cron'"
# 4. the runner
: > "$RUN/calls"; rm -f "$RUN/max"
START=$(date +%s); DOT_CRON_PARALLEL=4 bash "$HOME/bin/dot-cron-runner"; ELAPSED=$(( $(date +%s) - START ))
check "every schedule:run ran exactly once (10 apps)" "[ \$(grep -c 'schedule:run' '$RUN/calls') = 10 ]"
check "both queue workers ran once" "[ \$(grep -c 'queue:work' '$RUN/calls') = 2 ]"
check "all schedule:run calls came before any queue:work" "[ \$(grep -n 'queue:work' '$RUN/calls' | head -1 | cut -d: -f1) -gt 10 ]"
check "never more than 4 commands at once (saw $(cat "$RUN/max"))" "[ \$(cat '$RUN/max') -le 4 ]"
check "the pass took less than 12 s (6 slow apps of 1 s, 4 at a time)" "[ $ELAPSED -lt 12 ]"
check "last-run.log reports the failing command" "grep -q 'failed=1' '$HOME/.dot-cron.d/last-run.log' && grep -q 'FAILED:.*failer' '$HOME/.dot-cron.d/last-run.log'"
check "last-run.log counts 10 schedule and 2 queue commands" "grep -q 'schedule=10 queue=2' '$HOME/.dot-cron.d/last-run.log'"
# 5. timeout kills a hanging command and the pass still ends
START=$(date +%s); DOT_CRON_TIMEOUT=1 DOT_CRON_PARALLEL=4 bash "$HOME/bin/dot-cron-runner"; ELAPSED=$(( $(date +%s) - START ))
check "a command over DOT_CRON_TIMEOUT is killed (pass took $ELAPSED s, hang would be 5 s)" "grep -q 'hang' '$HOME/.dot-cron.d/last-run.log' && [ $ELAPSED -lt 9 ]"
# 6. revert
bash "$HOSTSH" revert tasks auction >/dev/null 2>&1
check "revert puts tasks' two lines back with the marker" "[ \$(grep -c '# dot-tasks' '$CRONTAB_FILE') = 2 ] && [ ! -f '$HOME/.dot-cron.d/tasks.cron' ]"
check "revert leaves the other moved apps in their files" "[ -f '$HOME/.dot-cron.d/slow1.cron' ]"
# 7. status prints without error
bash "$HOSTSH" status >/dev/null 2>&1; check "status runs" "[ $? -eq 0 ]"
[ $FAIL -eq 0 ] && echo "ALL PASSED" || { echo "SOME FAILED"; exit 1; }
