#!/usr/bin/env bash
# Adds the signed sign-in secret pair (InfoDot <-> platform) for one or more platforms to your local secrets file.
# Then `tools/apply-secrets.sh infodot <platform> ...` writes both sides to the hosts. Idempotent per platform:
# a platform that already has its pair is left alone. Values are never printed.
#
#   [SECRETS_FILE=~/dot-ecosystem-secrets.env] tools/signin-secret.sh agents central engage forms press projects sheet
set -euo pipefail
[ $# -gt 0 ] || { echo "usage: $0 <platform> [...]" >&2; exit 2; }
FILE="${SECRETS_FILE:-$HOME/dot-ecosystem-secrets.env}"
REDEEM_URL="${INFODOT_REDEEM_URL:-https://infodot.co.za/api/ecosystem/redeem}"
[ -f "$FILE" ] || { echo "no secrets file at $FILE (run tools/ecosystem-secrets.sh first)" >&2; exit 1; }
command -v openssl >/dev/null || { echo "openssl is required" >&2; exit 1; }
umask 077
added=""
for k in "$@"; do
  case "$k" in *[!a-z0-9]*|'') echo "invalid platform key: $k" >&2; exit 2 ;; esac
  U="$(printf '%s' "$k" | tr '[:lower:]' '[:upper:]')"
  if grep -qE "^InfoDot:[[:space:]]+ECOSYSTEM_SECRET_${U}=" "$FILE"; then echo "$k: pair already exists, left alone"; continue; fi
  V="$(openssl rand -hex 32)"
  {
    echo
    echo "# --- signed sign-in: $k (InfoDot ECOSYSTEM_SECRET_$U and the app's INFODOT_SERVICE_SECRET are the SAME value)"
    echo "InfoDot:    ECOSYSTEM_SECRET_$U=$V"
    echo "Dot.$k:     INFODOT_SERVICE_SECRET=$V"
    echo "Dot.$k:     INFODOT_PLATFORM_KEY=$k"
    echo "Dot.$k:     INFODOT_REDEEM_URL=$REDEEM_URL"
  } >> "$FILE"
  added="$added $k"
done
chmod 600 "$FILE"
if [ -n "$added" ]; then echo "added sign-in pair(s) to $FILE (not shown) for:$added"; echo "Next: tools/apply-secrets.sh infodot$added"; fi
