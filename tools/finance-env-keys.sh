#!/usr/bin/env bash
# TEMPORARY (added 2026-11-13). Delete this script, its test (tools/test-finance-env-keys.sh), its CI job and its step in
# .github/workflows/deploy.yml once ONE deploy has run it on the host. It is the only place the old setting names may appear.
#
# Payments moved to Dot.Finance and the code now reads new setting names. The host .env was written with the old ones, and a
# deploy caches config straight after `git reset --hard`: without this step the licence link would be read as "not set" and
# go quiet with no error. Run by deploy.yml on the host, BEFORE the config cache is built:
#
#   bash tools/finance-env-keys.sh <app folder that holds .env>
#
# For each pair below: when the old name is set and the new one is not, the NAME on that line is rewritten and the value is
# kept byte for byte. When both are set, or only the new one, the line is left alone. Before the first change .env is copied
# to .env.bak-finance-keys-<UTC time> beside it (mode 600). The file is rebuilt beside .env and checked (same number of lines,
# every value unchanged) before it replaces it. Running it again changes nothing and makes no backup. Only names are printed,
# never a value.
set -euo pipefail

PAIRS="BILLING_ENTITLEMENT_SECRET:FINANCE_ENTITLEMENT_SECRET
LICENCE_BILLING_URL:LICENCE_FINANCE_URL
LICENCE_BILLING_CONNECTED:LICENCE_FINANCE_CONNECTED
LICENCE_BILLING_PLATFORM:LICENCE_FINANCE_PLATFORM
LICENCE_BILLING_FAILURE_BACKOFF:LICENCE_FINANCE_FAILURE_BACKOFF
SEMINARS_BILLING_CALLBACK_SECRET:SEMINARS_FINANCE_CALLBACK_SECRET"

die() { echo "finance-env-keys: $*" >&2; exit 1; }
[ $# -eq 1 ] || die "usage: $0 <app folder that holds .env>"
ENV_FILE="$1/.env"
[ -f "$ENV_FILE" ] || die "no .env in $1"
[ -w "$ENV_FILE" ] || die ".env in $1 is not writable"

has_key() { grep -q "^$1=" "$ENV_FILE"; }

RENAME=""   # "OLD:NEW" lines to apply
while IFS=: read -r OLD NEW; do
  if has_key "$OLD" && has_key "$NEW"; then
    echo "== settings: $NEW is already set; the old $OLD line is left in place (unused, can be deleted by hand)"
  elif has_key "$OLD"; then
    RENAME="${RENAME:+$RENAME$'\n'}$OLD:$NEW"
  fi
done <<< "$PAIRS"

if [ -z "$RENAME" ]; then
  echo "== settings: no old payment setting name to rename (nothing changed, no backup made)"
  exit 0
fi

umask 077
BACKUP="$ENV_FILE.bak-finance-keys-$(date -u +%Y%m%dT%H%M%SZ)"
cp -p "$ENV_FILE" "$BACKUP"
chmod 600 "$BACKUP"

TMP="$(mktemp "$ENV_FILE.tmp.XXXXXX")"
trap 'rm -f "$TMP"' EXIT
# Only the text before the first "=" changes, and only on a line whose name is exactly an old one.
awk -v pairs="$(printf '%s' "$RENAME" | tr '\n' ' ')" '
  BEGIN { n = split(pairs, p, " "); for (i = 1; i <= n; i++) { split(p[i], kv, ":"); map[kv[1]] = kv[2] } }
  { line = $0; eq = index($0, "="); if (eq > 1) { key = substr($0, 1, eq - 1); if (key in map) { line = map[key] substr($0, eq) } } printf "%s%s", (NR > 1 ? "\n" : ""), line }
' "$ENV_FILE" > "$TMP"
# awk wrote no final newline; put one back only if .env ends with one, so not a single byte is added or lost.
if [ -z "$(tail -c 1 "$ENV_FILE")" ]; then echo >> "$TMP"; fi

# Nothing but names may differ: same number of lines, and identical text from the first "=" of every line onwards.
values() { sed 's/^[^=]*//' "$1"; }
[ "$(wc -l < "$ENV_FILE")" = "$(wc -l < "$TMP")" ] && [ "$(values "$ENV_FILE" | cksum)" = "$(values "$TMP" | cksum)" ] \
  || die "the rewritten .env did not match the original apart from names; .env was NOT changed (copy kept at $(basename "$BACKUP"))"
while IFS=: read -r OLD NEW; do
  grep -q "^$NEW=" "$TMP" && ! grep -q "^$OLD=" "$TMP" || die "$OLD was not renamed cleanly; .env was NOT changed (copy kept at $(basename "$BACKUP"))"
done <<< "$RENAME"

# Written through the existing file so its owner and mode stay as they were.
cat "$TMP" > "$ENV_FILE"

echo "== settings: copy of .env kept beside it as $(basename "$BACKUP") (mode 600)"
while IFS=: read -r OLD NEW; do
  echo "== settings: renamed $OLD to $NEW (value unchanged)"
done <<< "$RENAME"
