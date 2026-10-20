#!/usr/bin/env bash
# Adds the shared secret between InfoDot and the Dot Points service (Dot.Dopemine) to your local secrets file, then
# `tools/apply-secrets.sh infodot dopemine` writes it to both hosts. Idempotent: does nothing if the pair already exists.
# The value is never printed.
#
#   [SECRETS_FILE=~/dot-ecosystem-secrets.env] tools/points-secret.sh
set -euo pipefail
FILE="${SECRETS_FILE:-$HOME/dot-ecosystem-secrets.env}"
[ -f "$FILE" ] || { echo "no secrets file at $FILE (run tools/ecosystem-secrets.sh first)" >&2; exit 1; }
if grep -q '^InfoDot:[[:space:]]*DOPEMINE_INGEST_SECRET=' "$FILE"; then echo "the pair already exists in $FILE"; exit 0; fi
umask 077
V="$(openssl rand -hex 32)"
{
  echo
  echo "########## 5. Dot Points (InfoDot -> Dot.Dopemine ledger) ##########"
  echo "# Dopemine holds DOPEMINE_INGEST_SECRET_INFODOT, InfoDot holds DOPEMINE_INGEST_SECRET: the SAME value."
  echo "InfoDot:       DOPEMINE_INGEST_SECRET=$V"
  echo "InfoDot:       DOPEMINE_URL=https://dopemine.infodot.co.za"
  echo "Dot.dopemine:  DOPEMINE_INGEST_SECRET_INFODOT=$V"
} >> "$FILE"
chmod 600 "$FILE"
echo "added the InfoDot <-> Dopemine points secret to $FILE (not shown). Next: tools/apply-secrets.sh infodot dopemine"
