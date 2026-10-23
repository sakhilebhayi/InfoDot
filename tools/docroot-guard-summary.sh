#!/usr/bin/env bash
# Markdown job summary for the App Folder Guard docroot modes. Reads only files the workflow already wrote (status
# codes and result words, no file contents):
#   tools/docroot-guard-summary.sh <mode> <actions.txt> <before.txt> <after.txt> <probe.txt> [<undo.txt>]
# actions.txt = lines "<host>:<key>: <result ...>" from tools/docroot-guard-host.sh; before/after = tools/site-status.sh
# output; probe.txt = tools/probe-exposure.sh output. Any missing file is treated as empty.
set -uo pipefail
MODE="${1:-}"; ACT="${2:-/dev/null}"; BEF="${3:-/dev/null}"; AFT="${4:-/dev/null}"; PRB="${5:-/dev/null}"; UND="${6:-/dev/null}"
for f in ACT BEF AFT PRB UND; do [ -r "${!f}" ] || printf -v "$f" '%s' /dev/null; done
echo "### App Folder Guard: $MODE"
echo
echo "| pair | docroot .htaccess | / | /up | /login | sensitive paths under /<key>/ | undo |"
echo "|---|---|---|---|---|---|---|"
grep -E '^[a-z0-9-]+:[a-z0-9-]+: ' "$ACT" | while IFS= read -r line; do
  pair="${line%%: *}"; res="${line#*: }"; host="${pair%%:*}"
  b="$(grep "^$host: " "$BEF" | head -1 | sed "s/^$host: //")"; a="$(grep "^$host: " "$AFT" | head -1 | sed "s/^$host: //")"
  col() { # $1 = path, from a status line
    printf '%s' "$2" | awk -v p="$1" '{for(i=1;i<NF;i++) if($i==p){print $(i+1); exit}}'
  }
  cell() { local x y; x="$(col "$1" "$b")"; y="$(col "$1" "$a")"; if [ -z "$x$y" ]; then echo "-"; elif [ "$x" = "$y" ]; then echo "$x (same)"; else echo "$x to $y (CHANGED)"; fi; }
  if grep -q "^EXPOSED persistent: $pair " "$PRB"; then ex="PERSISTENT: $(grep "^EXPOSED persistent: $pair " "$PRB" | awk '{print $4}' | paste -sd, -)"
  elif grep -q "^EXPOSED transient: $pair " "$PRB"; then ex="transient only: $(grep "^EXPOSED transient: $pair " "$PRB" | awk '{print $4}' | paste -sd, -)"
  elif grep -q "(EXPOSED)" "$PRB"; then ex="EXPOSED (single pass, see log)"
  elif [ -s "$PRB" ]; then ex="none answered 200"
  else ex="not probed"; fi
  und="$(grep "^$pair: " "$UND" | head -1 | sed "s/^$pair: //")"
  echo "| $pair | $res | $(cell /) | $(cell /up) | $(cell /login) | $ex | ${und:--} |"
done
