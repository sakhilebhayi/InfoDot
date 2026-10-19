#!/usr/bin/env bash
# Audits the lockfiles on the default branch of every Dot repository: `composer audit --locked` and
# `npm audit --package-lock-only --omit=dev`. Prints a markdown table (and appends it to $GITHUB_STEP_SUMMARY
# when set); exits 1 if any repository has an advisory. The repositories are public, so the files are read
# with the API (GH_TOKEN optional but avoids rate limits). Needs: gh, composer, npm, python3.
#   tools/audit-dependencies.sh [Repo ...]      default: every repository below
set -uo pipefail
OWNER="${OWNER:-sakhilebhayi}"
REPOS=("$@"); [ ${#REPOS[@]} -gt 0 ] || REPOS=(Agents Analytics Auction Billing Central Charts Design Dopemine Ehail Emall Engage Farms Files Finance Forms HR Memory Mines Notify Plug Press Projects Pulse Sheet Tasks Tutor docs)
W="$(mktemp -d)"; trap 'rm -rf "$W"' EXIT
FOUND=0
OUT="| Repo | composer advisories | npm (prod) high/critical |\n|---|---|---|\n"
for R in "${REPOS[@]}"; do
  mkdir -p "$W/$R"; B=$(gh repo view "$OWNER/Dot.$R" --json defaultBranchRef --jq .defaultBranchRef.name 2>/dev/null)
  [ -n "$B" ] || { OUT+="| $R | repo not readable | |\n"; FOUND=1; continue; }
  P=""; [ "$R" = Charts ] && P="backend/"   # Dot.Charts keeps its Laravel app in backend/
  for f in composer.json composer.lock package-lock.json; do
    gh api "repos/$OWNER/Dot.$R/contents/$P$f?ref=$B" -H "Accept: application/vnd.github.raw" > "$W/$R/$f" 2>/dev/null || rm -f "$W/$R/$f"
  done
  C="n/a"; if [ -f "$W/$R/composer.lock" ] && [ -f "$W/$R/composer.json" ]; then
    C=$(cd "$W/$R" && composer audit --locked --no-interaction --format=json 2>/dev/null | python3 -c "
import json,sys
try:
  d=json.load(sys.stdin); a=d.get('advisories',{}); a={} if isinstance(a,list) else a; n=sum(len(v) for v in a.values())
  print('0' if n==0 else '%d (%s)'%(n, ', '.join(sorted(a))[:80]))
except Exception: print('audit error')")
  fi
  N="n/a"; if [ -f "$W/$R/package-lock.json" ]; then
    N=$(cd "$W/$R" && npm audit --package-lock-only --omit=dev --json 2>/dev/null | python3 -c "
import json,sys
try:
  v=json.load(sys.stdin)['metadata']['vulnerabilities']; print('%d'%(v.get('high',0)+v.get('critical',0)))
except Exception: print('audit error')")
  fi
  case "$C" in 0|n/a) ;; *) FOUND=1;; esac
  case "$N" in 0|n/a) ;; *) FOUND=1;; esac
  OUT+="| $R | $C | $N |\n"
done
printf "$OUT"
[ -n "${GITHUB_STEP_SUMMARY:-}" ] && { echo "## Dependency audit ($(date -u +%Y-%m-%d))"; printf "$OUT"; } >> "$GITHUB_STEP_SUMMARY"
[ "$FOUND" = 0 ] && echo "No advisories." || echo "ADVISORIES FOUND (or a repository/audit could not be read)."
exit "$FOUND"
