#!/usr/bin/env bash
# The one cron job of the account. Every Dot app used to have its own `* * * * *` crontab lines, 43 of them
# firing in the same second; the shared host ran out of processes ("fork: Resource temporarily
# unavailable", 2026-10-19). This runner is started once a minute and runs the apps' commands itself, at most
# DOT_CRON_PARALLEL (default 4) at a time.
#
#   ~/.dot-cron.d/<app>.cron   one shell command per line (blank and '#' lines ignored); written by the app's
#                              deploy pipeline, never by hand.
#
# Pass 1 runs every `artisan schedule:run` line (quick, must happen every minute). Pass 2 runs the
# `artisan queue:work` lines; it starts only when the previous pass 2 has finished (flock). The two passes have SEPARATE
# locks: the schedule lock is released as soon as pass 1 is done, so a slow queue pass can no longer make the next
# minute's schedule pass skip (every app's scheduler heartbeat must be fresh within 180 s). Before a queue worker is
# started for an app, ~/bin/dot-queue-precheck.php asks its database whether any job is waiting (one cheap query, no
# framework boot); an empty queue skips the boot, any doubt runs the worker as before. All commands run under `nice`
# so the websites win when the account's CPU is limited. The runner waits
# DOT_CRON_DELAY seconds (default 30) before it starts, so it does not coincide with crontab lines at second 0. No command may
# run longer than DOT_CRON_TIMEOUT seconds (default 115). The last pass is summarised in
# ~/.dot-cron.d/last-run.log (time, command counts, failures, seconds) so a slow minute is visible.
set -u
D="${DOT_CRON_DIR:-$HOME/.dot-cron.d}"
P="${DOT_CRON_PARALLEL:-2}"
T="${DOT_CRON_TIMEOUT:-115}"
# Lower CPU priority for everything the runner starts (where `nice` exists), and the queue pre-check script (optional).
NICE=""; command -v nice >/dev/null 2>&1 && NICE="nice -n 10"
PRE="${DOT_QUEUE_PRECHECK:-$HOME/bin/dot-queue-precheck.php}"; [ -f "$PRE" ] || PRE=""
[ -d "$D" ] || exit 0
mkdir -p "$D/locks"
# Start away from second 0: the apps that still keep their own crontab lines (and the web workers) all start
# there, and the host kills processes when the burst is too big (2026-10-19: exit 137 and 125 in the pilot).
sleep "${DOT_CRON_DELAY:-30}"
# One schedule pass at a time: if the previous pass is still running, skip this minute rather than pile up.
exec 7>"$D/locks/schedule-pass.lock"
flock -n 7 || { echo "$(date -u +%Y-%m-%dT%H:%M:%SZ) skipped: previous pass still running" > "$D/last-skip.log"; exit 0; }
START=$(date +%s)
FAILED="$D/.failed.$$"; TIMES="$D/.times.$$"; SKIPPED="$D/.skipped.$$"; : > "$FAILED"; : > "$TIMES"; : > "$SKIPPED"
trap 'rm -f "$FAILED" "$TIMES" "$SKIPPED"' EXIT

select_lines() { # $1 = artisan command to select, e.g. schedule:run
  cat "$D"/*.cron 2>/dev/null | grep -v '^[[:space:]]*#' | grep -v '^[[:space:]]*$' | grep -E "artisan $1( |\$)" || true
}

run_pass() { # $1 = artisan command, $2 = "precheck" to skip apps whose database queue is empty (queue pass only)
  local lines pre=""
  lines="$(select_lines "$1")"
  [ -n "$lines" ] || return 0
  [ "${2:-}" = precheck ] && pre="$PRE"
  printf '%s\n' "$lines" | tr '\n' '\0' | DOT_T="$T" DOT_FAILED="$FAILED" DOT_TIMES="$TIMES" DOT_DIR="$D" DOT_NICE="$NICE" DOT_PRE="$pre" DOT_SKIPPED="$SKIPPED" \
    xargs -0 -n 1 -P "$P" sh -c '
      S=$(date +%s)
      if [ -n "$DOT_PRE" ]; then
        D1=$(printf "%s" "$1" | sed -n "s/^cd \([^ ]*\) &&.*/\1/p"); B1=$(printf "%s" "$1" | sed -n "s/.*exec \([^ ]*\) artisan.*/\1/p"); [ -n "$B1" ] || B1=php
        if [ -n "$D1" ]; then
          $DOT_NICE timeout -k 2 10 "$B1" "$DOT_PRE" "$D1" >/dev/null 2>&1; PRC=$?
          if [ "$PRC" -eq 10 ]; then echo "0s rc=0 SKIPPED-EMPTY-QUEUE $1" >> "$DOT_TIMES"; echo x >> "$DOT_SKIPPED"; exit 0; fi
        fi
      fi
      timeout -k 5 "$DOT_T" $DOT_NICE sh -c "$1"; RC=$?; E=$(date +%s)
      echo "$((E - S))s rc=$RC $1" >> "$DOT_TIMES"
      if [ "$RC" -ne 0 ]; then
        echo "rc=$RC $1" >> "$DOT_FAILED"
        # keep the end of the app'"'"'s own cron log (the command writes it; the next minute overwrites it)
        DIR=$(printf "%s" "$1" | sed -n "s/^cd \([^ ]*\) &&.*/\1/p"); LOG=$(printf "%s" "$1" | sed -n "s/.*> \([^ ]*\) 2>&1.*/\1/p")
        if [ -n "$DIR" ] && [ -n "$LOG" ] && [ -f "$DIR/$LOG" ]; then
          { echo "--- $(date -u +%H:%M:%SZ) rc=$RC $DIR"; tail -8 "$DIR/$LOG" | cut -c1-200; } >> "$DOT_DIR/failures.log"
        fi
      fi' _
}

N1=$(select_lines 'schedule:run' | grep -c . || true)
run_pass 'schedule:run'
SCHED_END=$(date +%s)
flock -u 7   # pass 1 is done: the next minute's schedule pass must not wait for a slow queue pass
N2=0
exec 8>"$D/locks/queue-pass.lock"
if flock -n 8; then
  N2=$(select_lines 'queue:work' | grep -c . || true)
  run_pass 'queue:work' precheck
  flock -u 8
fi
END=$(date +%s)
{
  echo "$(date -u +%Y-%m-%dT%H:%M:%SZ) schedule=${N1} queue=${N2} failed=$(grep -c . "$FAILED" || true) seconds=$((END - START)) parallel=$P sched_seconds=$((SCHED_END - START)) skipped_empty=$(grep -c . "$SKIPPED" || true)"
  head -10 "$FAILED" | cut -c1-200 | sed 's/^/  FAILED: /'
  echo "  slowest:"; sort -rn "$TIMES" | head -5 | cut -c1-160 | sed 's/^/    /'
} > "$D/last-run.log"
# failures.log keeps the tails of failing commands' own logs; bounded
[ -f "$D/failures.log" ] && tail -n 200 "$D/failures.log" > "$D/failures.log.t" && mv "$D/failures.log.t" "$D/failures.log"
