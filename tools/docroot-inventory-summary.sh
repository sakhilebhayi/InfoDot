#!/usr/bin/env bash
# Markdown job summary for the App Folder Guard mode docroot-inventory. Reads only the file the workflow already wrote
# (the output of tools/docroot-inventory-host.sh: names, sizes, yes/no and counts and, for a custom docroot index.php shim, a
# sanitised classification of what it does; no file contents):
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
cell() { # a value that goes into a markdown table cell: no pipe, no backtick
  printf '%s' "$1" | tr '|`' '//'
}
shimcell() { # standard | missing | custom (SAFE-TO-DROP) | custom (DOES-SOMETHING(flags))
  local sh sv
  sh="$(val "$1" shim)"
  if [ "$sh" = custom ]; then
    sv="$(val "$1" shim_verdict)"; sv="${sv#SHIM-}"
    [ "$sv" = "-" ] && sv="not classified"
    printf '%s' "custom ($(cell "$sv"))"
  else
    cell "$sh"
  fi
}
customs=""
for pair in $pairs; do
  v="$(val "$pair" verdict)"
  syms="$(grep -c "^$pair: entry: symlink " "$INV")" || true
  [ "$(val "$pair" shim)" = custom ] && customs="$customs $pair"
  echo "| $pair | $(val "$pair" layout) | $(shimcell "$pair") | $(val "$pair" public_htaccess) | $(val "$pair" htaccess_diff_signal) | ${syms:-0} | $(val "$pair" uploads_via_symlink) | $(val "$pair" cron_lines_mentioning_app) | **${v%%(*}** |"
done
echo
if [ -n "$customs" ]; then
  echo "Custom docroot \`index.php\` shims (what each one does; a sanitised classification, no code and no string value is shown):"
  echo
  echo "| pair | shim verdict | requires public/index.php | requires other | sets ini/env | sends headers | maintenance include | app path differs | defines | other code | size |"
  echo "|---|---|---|---|---|---|---|---|---|---|---|"
  for pair in $customs; do
    echo "| $pair | $(cell "$(val "$pair" shim_verdict)") | $(val "$pair" shim_requires_public_index) | $(cell "$(val "$pair" shim_requires_other)") | $(val "$pair" shim_sets_ini_or_env) | $(val "$pair" shim_sends_headers) | $(val "$pair" shim_has_maintenance_include) | $(val "$pair" shim_app_path_differs) | $(val "$pair" shim_defines) | $(val "$pair" shim_other_code) | $(cell "$(val "$pair" docroot_index_php)") |"
  done
  echo
  echo "Statements of each custom shim (at most 40 lines per pair; the raw output artifact has the same lines):"
  echo
  for pair in $customs; do
    if grep -q "^$pair: shim_extra: L" "$INV"; then
      echo "- $pair:"
      grep "^$pair: shim_extra: L" "$INV" | head -40 | while IFS= read -r line; do echo "  - \`$(cell "${line#*: shim_extra: }")\`"; done
    fi
  done
  echo
fi
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
