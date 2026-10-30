#!/usr/bin/env bash
# The static-analysis baseline may only shrink. phpstan-baseline.max holds the ceiling (the number of legacy errors the baseline
# accepts). Regenerating the baseline to hide a new error raises the count and fails here. When errors are fixed, lower the ceiling
# in the same change: the script says so.
set -euo pipefail
cd "$(dirname "$0")/.."
n=$(grep -E '^\s+count: [0-9]+' phpstan-baseline.neon | awk '{s += $2} END {print s + 0}')
max=$(tr -d '[:space:]' < phpstan-baseline.max)
if [ "$n" -gt "$max" ]; then echo "phpstan-baseline.neon accepts $n errors; the ceiling is $max. Fix the new errors instead of baselining them."; exit 1; fi
if [ "$n" -lt "$max" ]; then echo "The baseline is down to $n (ceiling $max). Lower phpstan-baseline.max to $n so it cannot creep back."; exit 1; fi
echo "phpstan baseline: $n legacy errors (ceiling $max)"
