#!/usr/bin/env bash
# Runs ON the host (piped over ssh by .github/workflows/cron-runner.yml). Installs and manages the
# bounded-concurrency cron runner (tools/dot-cron-runner.sh) and moves apps' cron lines into command files.
#
#   dot-cron-host.sh status
#   dot-cron-host.sh install                 add the one runner line to the crontab (runner script must be in ~/bin)
#   dot-cron-host.sh migrate <key> [<key>]   move the app's `# dot-<key>` crontab lines into ~/.dot-cron.d/<key>.cron
#   dot-cron-host.sh revert  <key> [<key>]   undo: put the app's lines back into the crontab, delete its file
#
# Same safety rules as the deploy pipelines' crontab writer: one writer (flock on ~/.dot-crontab.lock), a read is
# trusted only when non-empty or the host says "no crontab", a timestamped backup before every write, and a
# read-back check that no line of anyone else changed; on a mismatch the previous crontab is restored.
set -euo pipefail
MODE="${1:-status}"; shift || true
BIN="$HOME/bin/dot-cron-runner"; DIR="$HOME/.dot-cron.d"; RMARK="# dot-cron-runner"
RLINE="* * * * * $BIN > $DIR/runner.out 2>&1 $RMARK"

read_crontab() { # $1 = file
  local i err; err="$(mktemp)"
  for i in 1 2 3 4 5; do
    if crontab -l > "$1" 2> "$err"; then [ -s "$1" ] && { rm -f "$err"; return 0; }
    elif grep -qi "no crontab for" "$err"; then : > "$1"; rm -f "$err"; return 0; fi
    sleep 2
  done
  echo "crontab -l answered empty or failed five times: $(cat "$err")" >&2; rm -f "$err"; return 1
}

with_lock() {
  command -v flock > /dev/null || { echo "flock is not installed on the host" >&2; exit 1; }
  exec 9>"$HOME/.dot-crontab.lock"; flock -w 120 9 || { echo "could not take the crontab lock within 120 s" >&2; exit 1; }
}

normalize() { grep -v '^[[:space:]]*$' "$1" | grep -v '^# \(DO NOT EDIT\|(.* installed on\|(Cron version\)' | sed -E 's/[[:space:]]+$//' | sort; }

# write_crontab <new file> <old file> <expected number of lines of others>  (others = lines not in $4 pattern)
write_crontab() {
  local new="$1" old="$2" chk; chk="$(mktemp)"
  cp "$old" "$HOME/.dot-crontab.backup-$(date -u +%Y%m%dT%H%M%S)"
  crontab "$new"
  read_crontab "$chk" || { crontab "$old"; echo "read-back failed; previous crontab restored" >&2; exit 1; }
  # Some crontab implementations add header comments, trim trailing blanks or drop empty lines on write:
  # compare without those, and show what differs (cron lines hold paths only, no secrets) if it still does.
  if ! cmp -s <(normalize "$new") <(normalize "$chk"); then
    echo "read-back differs from what was written; difference (< written, > read back):" >&2
    diff <(normalize "$new") <(normalize "$chk") | head -20 | cut -c1-200 >&2 || true
    crontab "$old"; echo "previous crontab restored" >&2; exit 1
  fi
  rm -f "$chk"
}

case "$MODE" in
status)
  echo "== runner script: $( [ -x "$BIN" ] && echo "present ($BIN)" || echo "MISSING" )"
  CT="$(mktemp)"; read_crontab "$CT"
  echo "== runner crontab line: $(grep -c "$RMARK" "$CT") (expected 1 once installed)"
  echo "== crontab lines still firing per app (marker lines): $(grep -c '^\* \* \* \* \*\|^[0-9*/,-]* \* \* \* \*' "$CT")"
  grep -o '# dot-[a-z0-9-]*' "$CT" | sort | uniq -c | sort -rn | sed 's/^/   /'
  echo "== command files in $DIR:"; ls -1 "$DIR"/*.cron 2>/dev/null | sed "s#^$HOME/##" || echo "   none"
  echo "== last pass:"; cat "$DIR/last-run.log" 2>/dev/null || echo "   none yet"
  rm -f "$CT" ;;
install)
  [ -x "$BIN" ] || { echo "runner script missing or not executable: $BIN" >&2; exit 1; }
  mkdir -p "$DIR" "$DIR/locks"; with_lock
  OLD="$(mktemp)"; NEW="$(mktemp)"; read_crontab "$OLD"
  { grep -v "$RMARK" "$OLD" || true; echo "$RLINE"; } > "$NEW"
  write_crontab "$NEW" "$OLD"; echo "runner line installed; $(grep -vc "$RMARK" "$OLD" || true) other line(s) untouched" ;;
migrate|revert)
  [ $# -ge 1 ] || { echo "give at least one app key" >&2; exit 2; }
  mkdir -p "$DIR"; with_lock
  OLD="$(mktemp)"; NEW="$(mktemp)"; read_crontab "$OLD"; cp "$OLD" "$NEW"
  if [ "$MODE" = migrate ]; then
    grep -q "$RMARK" "$OLD" || { echo "the runner line is not in the crontab: run install first" >&2; exit 1; }
    [ -x "$BIN" ] || { echo "runner script missing: $BIN" >&2; exit 1; }
  fi
  for K in "$@"; do
    M="# dot-$K"; F="$DIR/$K.cron"
    if [ "$MODE" = migrate ]; then
      LINES="$(grep -F -- "$M" "$OLD" | grep -E 'artisan (schedule:run|queue:work)' || true)"
      [ -n "$LINES" ] || { echo "$K: no schedule:run/queue:work lines marked $M; skipped"; continue; }
      printf '%s\n' "$LINES" | sed -E "s/^[^ ]+ [^ ]+ [^ ]+ [^ ]+ [^ ]+ //; s/ *# dot-$K\$//" > "$F.tmp" && mv "$F.tmp" "$F"
      grep -vF -- "$M" "$NEW" > "$NEW.t" || true; mv "$NEW.t" "$NEW"
      echo "$K: $(grep -c . "$F") command(s) moved to $F"
    else
      [ -f "$F" ] || { echo "$K: no $F; skipped"; continue; }
      while IFS= read -r cmd; do [ -n "$cmd" ] && echo "* * * * * $cmd $M" >> "$NEW"; done < "$F"
      rm -f "$F"; echo "$K: command(s) put back into the crontab"
    fi
  done
  write_crontab "$NEW" "$OLD"; echo "crontab updated (backup kept as ~/.dot-crontab.backup-*)" ;;
*) echo "unknown mode $MODE" >&2; exit 2 ;;
esac
