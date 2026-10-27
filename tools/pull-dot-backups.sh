#!/bin/bash
# Pulls ~/dot-backups from the Dot host to this Mac (read-only key, no --delete), then checks what arrived.
# Exit 0 = pulled and every newest archive matches its checksum and is fresh; non-zero = something needs a look.
set -u
SRC="${SRC:-infodotc@infodot.co.za:dot-backups/}"
DEST="${DEST:-$HOME/Backups/dot-backups}"
LOG="${LOG:-$HOME/Backups/pull.log}"
KEY="${KEY:-$HOME/.ssh/dot_backup_pull}"
MAX_AGE_H="${MAX_AGE_H:-36}"
KEEP_DAYS="${KEEP_DAYS:-180}"
NOTIFY="${NOTIFY:-1}"
LOCKDIR="$(dirname "$LOG")/.pull.lock"
mkdir -p "$DEST" "$(dirname "$LOG")"
mtime() { if [ "$(uname)" = Darwin ]; then stat -f %m "$1"; else stat -c %Y "$1"; fi; }
say() { printf '%s %s\n' "$(date '+%F %T')" "$*" >> "$LOG"; }
alert() { say "ALERT: $*"; [ "$NOTIFY" = 1 ] && osascript -e "display notification \"$*\" with title \"Dot backup pull\"" >/dev/null 2>&1; return 0; }
if ! mkdir "$LOCKDIR" 2>/dev/null; then
  find "$LOCKDIR" -maxdepth 0 -mmin +180 -exec rmdir {} \; 2>/dev/null
  mkdir "$LOCKDIR" 2>/dev/null || { say "another pull is running; skipped"; exit 0; }
fi
trap 'rmdir "$LOCKDIR" 2>/dev/null' EXIT
t0=$(date +%s)
out="$(rsync -a -e "ssh -i $KEY -o IdentitiesOnly=yes -o BatchMode=yes -o ConnectTimeout=20 -o ServerAliveInterval=30 -o ServerAliveCountMax=6" "$SRC" "$DEST/" 2>&1)"; rc=$?
if [ $rc -ne 0 ]; then alert "rsync failed (rc=$rc): $(printf '%s' "$out" | tail -n 1 | cut -c1-120)"; exit 1; fi
bad=0; apps=0; now=$(date +%s)
for d in "$DEST"/*/; do
  [ -d "$d" ] || continue
  app="$(basename "$d")"
  newest="$(ls -1 "$d"*.enc 2>/dev/null | sort | tail -n 1)"
  [ -n "$newest" ] || { say "no archive for $app"; continue; }
  apps=$((apps + 1))
  want="$(awk '{print $1}' "$newest.sha256" 2>/dev/null)"; have="$(shasum -a 256 "$newest" | awk '{print $1}')"
  if [ -z "$want" ] || [ "$want" != "$have" ]; then alert "$app: checksum mismatch or missing for $(basename "$newest")"; bad=$((bad + 1)); continue; fi
  age_h=$(( (now - $(mtime "$newest")) / 3600 ))
  if [ "$age_h" -gt "$MAX_AGE_H" ]; then alert "$app: newest archive is ${age_h}h old (limit ${MAX_AGE_H}h)"; bad=$((bad + 1)); fi
done
find "$DEST" -type f \( -name '*.enc' -o -name '*.enc.sha256' \) -mtime +"$KEEP_DAYS" -delete 2>/dev/null
say "pull done in $(( $(date +%s) - t0 ))s: $apps apps checked, $bad problem(s), $(du -sh "$DEST" | awk '{print $1}') on disk"
[ "$bad" -eq 0 ]
