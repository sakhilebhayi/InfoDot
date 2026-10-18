#!/usr/bin/env bash
# First install of a platform on the shared cPanel host, per docs/deployment.md: creates the docroot
# layout, clones the code, writes a production .env (SQLite to start with: no cPanel database needed),
# generates APP_KEY, installs composer packages. You run this on YOUR machine; it SSHes with your own
# SSH access. After it, run the platform's deploy.yml (adopt, then deploy).
#
#   SSH_TARGET=user@host [SECRETS_FILE=~/dot-ecosystem-secrets.env] tools/prepare-host.sh <platform> [...]
#
# Optional: REPO_BASE (default https://github.com/sakhilebhayi; the host must be able to read the
# repositories the same way the other Dot apps' deploys do), PHP_BIN (default php), BASE_DOMAIN
# (default infodot.co.za), DRY_RUN=1 (print the remote script, run nothing).
# Secrets for the platform (sign-in, Notify, Analytics) are taken from SECRETS_FILE (written by
# tools/ecosystem-secrets.sh) and written only into the remote .env.
set -euo pipefail
: "${SSH_TARGET:?set SSH_TARGET=user@host}"
[ $# -gt 0 ] || { echo "usage: $0 <platform> [...]" >&2; exit 2; }
REPO_BASE="${REPO_BASE:-https://github.com/sakhilebhayi}"; PHP_BIN="${PHP_BIN:-php}"; BASE_DOMAIN="${BASE_DOMAIN:-infodot.co.za}"
SECRETS_FILE="${SECRETS_FILE:-$HOME/dot-ecosystem-secrets.env}"
SSH_BIN="${SSH_BIN:-ssh}"
cap() { case "$1" in hr) echo HR;; *) echo "$(tr '[:lower:]' '[:upper:]' <<<"${1:0:1}")${1:1}";; esac; }

for K in "$@"; do
  case "$K" in tasks|auction|ehail|notify|farms|finance|emall|billing|hr|tutor|design|pulse|plug) ;; *) echo "unknown platform $K" >&2; exit 2;; esac
  HOST="$K.$BASE_DOMAIN"; REPO="$REPO_BASE/Dot.$(cap "$K").git"
  # secrets for this platform: lines "Dot.<k>:  NAME=value" from the generated file
  EXTRA=""
  if [ -r "$SECRETS_FILE" ]; then
    EXTRA="$(grep -E "^Dot\.$K:[[:space:]]+[A-Z_]+=" "$SECRETS_FILE" | sed -E "s/^Dot\.$K:[[:space:]]+//" || true)"
  else
    echo "note: $SECRETS_FILE not readable; the .env will have no shared secrets (INFODOT_SERVICE_SECRET etc.)" >&2
  fi
  REMOTE="$(cat <<REMOTE_EOF
set -euo pipefail
K='$K'; HOST='$HOST'; REPO='$REPO'; PHP='$PHP_BIN'; BASE_DOMAIN='$BASE_DOMAIN'
D="\$HOME/\$HOST"; APP="\$D/\$K"
mkdir -p "\$D"
if [ ! -d "\$APP/.git" ]; then git clone --branch main "\$REPO" "\$APP"; else echo "app folder exists: \$APP (left as is)"; fi
cd "\$APP"
if [ ! -f .env ]; then
  cp .env.example .env
  setenv() { if grep -q "^\$1=" .env; then sed "s|^\$1=.*|\$1=\$2|" .env > .env.tmp && mv .env.tmp .env; else printf '%s=%s\n' "\$1" "\$2" >> .env; fi; }
  setenv APP_ENV production; setenv APP_DEBUG false; setenv APP_URL "https://\$HOST"
  setenv LOG_LEVEL error; setenv SESSION_SECURE_COOKIE true
  setenv DB_CONNECTION sqlite; setenv DB_DATABASE "\$APP/database/database.sqlite"
  setenv SESSION_DRIVER database; setenv CACHE_STORE database; setenv QUEUE_CONNECTION database
  setenv BROADCAST_CONNECTION log
  setenv INFODOT_PLATFORM_KEY "\$K"; setenv INFODOT_REDEEM_URL "https://infodot.co.za/api/ecosystem/redeem"
  setenv DOT_INFODOT_URL "https://infodot.co.za"
  while IFS='=' read -r name value; do [ -n "\$name" ] && setenv "\$name" "\$value"; done <<'SECRETS_EOF'
$EXTRA
SECRETS_EOF
  # Notify links only when this platform has a Notify secret; Analytics stays off until Dot.Analytics is
  # live (set ANALYTICS_INGEST_URL=https://analytics.$BASE_DOMAIN/api/ingest/snapshots then).
  if grep -q '^NOTIFY_INGEST_SECRET=' .env; then setenv NOTIFY_INGEST_URL "https://notify.$BASE_DOMAIN/api/ingest/events"; fi
  mkdir -p database; touch database/database.sqlite
  echo "wrote .env (SQLite). Mail: set MAIL_* in \$APP/.env before relying on email."
else
  echo ".env exists (left as is)"
fi
command -v composer >/dev/null || { echo "composer is not on PATH for this SSH user" >&2; exit 1; }
composer install --no-dev --no-interaction --optimize-autoloader -q
grep -q '^APP_KEY=base64:' .env || "\$PHP" artisan key:generate --force
# docroot: shim, rewrite rules, links to the built assets (the deploy keeps build/ in step)
printf '<?php\nrequire __DIR__.%s;\n' "'/\$K/public/index.php'" > "\$D/index.php"
if [ -f public/.htaccess ]; then cp public/.htaccess "\$D/.htaccess"; else cat > "\$D/.htaccess" <<'HT'
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>
    RewriteEngine On
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
    RewriteCond %{HTTP:x-xsrf-token} .
    RewriteRule .* - [E=HTTP_X_XSRF_TOKEN:%{HTTP:X-XSRF-Token}]
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/\$
    RewriteRule ^ %1 [L,R=301]
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>
HT
fi
for f in images apple-touch-icon.png favicon-16x16.png favicon-32x32.png favicon.ico robots.txt; do [ -e "public/\$f" ] && ln -sfn "\$K/public/\$f" "\$D/\$f" || true; done
# keep the app folder itself from being served
[ -f "\$APP/.htaccess" ] || printf 'Require all denied\n' > "\$APP/.htaccess"
echo "prepared \$HOST. Next: gh workflow run deploy.yml --repo sakhilebhayi/\$(basename "\$REPO" .git) -f confirm=adopt  (then confirm=deploy)"
REMOTE_EOF
)"
  if [ "${DRY_RUN:-0}" = 1 ]; then echo "---- $K (dry run)"; echo "$REMOTE" | sed -E 's/(INFODOT_SERVICE_SECRET|SECRET[A-Z_]*)=[^ ]+/\1=<hidden>/'; continue; fi
  echo "== preparing $HOST"
  printf '%s\n' "$REMOTE" | $SSH_BIN "$SSH_TARGET" 'bash -s'
done
