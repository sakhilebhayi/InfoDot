#!/usr/bin/env bash
# Writes (or refreshes) the shared secrets of the Dot ecosystem into the host .env of apps that are ALREADY
# installed. Idempotent: an existing line is replaced, a missing one appended, nothing else is touched.
# Typical use: the sign-in secrets InfoDot itself needs (ECOSYSTEM_SECRET_<KEY>) before the platforms go live,
# and repairing a .env after the secrets file changed. You run this on YOUR machine with your own SSH access.
#
#   SSH_TARGET=user@host [SECRETS_FILE=~/dot-ecosystem-secrets.env] tools/apply-secrets.sh infodot [<platform> ...]
#
# "infodot" takes the lines starting "InfoDot:" in the secrets file; any other name takes "Dot.<name>:" lines.
# Trailing "# comments" on a line are dropped. Values are never printed. Optional: DRY_RUN=1 (show which names
# would be written, run nothing), SET_VARS (extra NAME=value lines for every target), SSH_BIN, PHP_BIN (default php), BASE_DOMAIN (default infodot.co.za).
set -euo pipefail
: "${SSH_TARGET:?set SSH_TARGET=user@host}"
[ $# -gt 0 ] || { echo "usage: $0 infodot|<platform> [...]" >&2; exit 2; }
SECRETS_FILE="${SECRETS_FILE:-$HOME/dot-ecosystem-secrets.env}"; SSH_BIN="${SSH_BIN:-ssh}"
PHP_BIN="${PHP_BIN:-php}"; BASE_DOMAIN="${BASE_DOMAIN:-infodot.co.za}"
[ -r "$SECRETS_FILE" ] || { echo "cannot read $SECRETS_FILE (run tools/ecosystem-secrets.sh first)" >&2; exit 1; }

for K in "$@"; do
  case "$K" in
    infodot) PREFIX='InfoDot:' ;;
    tasks|auction|ehail|notify|farms|finance|emall|billing|hr|tutor|design|pulse|plug) PREFIX="Dot\\.$K:" ;;
    *) echo "unknown target $K" >&2; exit 2 ;;
  esac
  LINES="$(grep -E "^${PREFIX}[[:space:]]+[A-Z_0-9]+=" "$SECRETS_FILE" | sed -E "s/^${PREFIX}[[:space:]]+//; s/[[:space:]]+#.*$//" || true)"
  # SET_VARS: extra NAME=value lines (newline separated) written to every target, e.g.
  #   SET_VARS="ANALYTICS_INGEST_URL=https://analytics.infodot.co.za/api/ingest/snapshots"
  if [ -n "${SET_VARS:-}" ]; then LINES="${LINES:+$LINES$'\n'}$SET_VARS"; fi
  [ -n "$LINES" ] || { echo "$K: no lines in $SECRETS_FILE, skipped"; continue; }
  if [ "${DRY_RUN:-}" = 1 ]; then echo "== $K would write: $(printf '%s\n' "$LINES" | cut -d= -f1 | paste -sd' ' -)"; continue; fi
  echo "== $K: writing $(printf '%s\n' "$LINES" | grep -c .) line(s)"
  $SSH_BIN "$SSH_TARGET" bash -s <<REMOTE_EOF
set -euo pipefail
K='$K'; PHP='$PHP_BIN'
if [ "\$K" = infodot ]; then
  D="\$HOME/infodot.$BASE_DOMAIN"; [ -d "\$D" ] || D="\$HOME/public_html"
  APP="\$(find "\$D" -maxdepth 2 -name artisan -type f -exec dirname {} \; | sort -u)"
  [ "\$(printf '%s\n' "\$APP" | grep -c .)" = 1 ] || { echo "expected exactly one Laravel app under \$D, found: \${APP:-none}" >&2; exit 1; }
else
  APP="\$HOME/\$K.$BASE_DOMAIN/\$K"
fi
cd "\$APP"; [ -f .env ] || { echo ".env missing in \$APP" >&2; exit 1; }
cp .env ".env.bak-secrets"; chmod 600 ".env.bak-secrets"
setenv() { if grep -q "^\$1=" .env; then grep -v "^\$1=" .env > .env.tmp; printf '%s=%s\n' "\$1" "\$2" >> .env.tmp; mv .env.tmp .env; else printf '%s=%s\n' "\$1" "\$2" >> .env; fi; }
n=0
while IFS='=' read -r name value; do [ -n "\$name" ] && { setenv "\$name" "\$value"; n=\$((n+1)); }; done <<'SECRETS_EOF'
$LINES
SECRETS_EOF
chmod 600 .env
echo "\$K: \$n variable(s) set in \$APP/.env (previous copy: .env.bak-secrets)"
if [ -f bootstrap/cache/config.php ]; then "\$PHP" artisan config:cache >/dev/null && echo "\$K: config cache refreshed"; fi
REMOTE_EOF
done
