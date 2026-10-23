#!/usr/bin/env bash
# Markdown job summary for the App Folder Guard mode docroot-inventory. Reads only the file the workflow already wrote
# (the output of tools/docroot-inventory-host.sh: names, sizes, yes/no and counts; no file contents):
#   tools/docroot-inventory-summary.sh <inventory.txt>
# A missing or empty file gives a table with no rows and a note.
set -uo pipefail
INV="${1:-/dev/null}"
[ -r "$INV" ] || INV=/dev/null
echo "### App Folder Guard: docroot-inventory (read-only)"
echo
echo "| pair | layout | docroot shim | public/.htaccess | docroot .htaccess vs public | docroot symlinks | uploads via symlink | cron lines (app folder) | verdict |"
echo "|---|---|---|---|---|---|---|---|---|"
val() { # $1 = pair, $2 = field -> value of the first matching line, or "-"
  awk -v p="$1: $2: " 'index($0, p) == 1 { print substr($0, length(p) + 1); f = 1; exit } END { if (!f) print "-" }' "$INV"
}
pairs="$(grep -E '^[a-z0-9-]+:[a-z0-9-]+: verdict: ' "$INV" | sed -E 's/^([a-z0-9-]+:[a-z0-9-]+): .*/\1/')"
for pair in $pairs; do
  v="$(val "$pair" verdict)"
  syms="$(grep -c "^$pair: entry: symlink " "$INV")" || true
  echo "| $pair | $(val "$pair" layout) | $(val "$pair" shim) | $(val "$pair" public_htaccess) | $(val "$pair" htaccess_diff_signal) | ${syms:-0} | $(val "$pair" uploads_via_symlink) | $(val "$pair" cron_lines_mentioning_app) | **${v%%(*}** |"
done
echo
if [ -z "$pairs" ]; then echo "No verdict lines found: the host script printed nothing usable (see the raw output artifact)."; fi
sline="$(grep '^summary: ' "$INV" | head -1)"
[ -n "$sline" ] && echo "$sline" && echo
if grep -qE '^[a-z0-9-]+:[a-z0-9-]+: reason: ' "$INV"; then
  echo "Reasons (why a pair is not READY):"
  echo
  grep -E '^[a-z0-9-]+:[a-z0-9-]+: reason: ' "$INV" | while IFS= read -r line; do
    pair="${line%%: reason: *}"; r="${line#*: reason: }"
    echo "- $pair: \`$r\`"
  done
  echo
fi
echo "Full raw output: artifact \`docroot-inventory\` of this run. Nothing was written on the host."
