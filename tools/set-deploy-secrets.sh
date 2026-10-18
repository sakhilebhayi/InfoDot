#!/usr/bin/env bash
# Sets the GitHub Actions secrets every platform's deploy.yml needs, on all platform repos at once.
# You run this on YOUR machine; it uses your gh login and your SSH key file. Nothing is printed except
# secret NAMES.
#
#   DEPLOY_HOST=<cpanel host name or ip> DEPLOY_USER=<cpanel ssh user> \
#   DEPLOY_KEY_FILE=~/.ssh/<deploy key, private half> [DEPLOY_PORT=22] \
#   tools/set-deploy-secrets.sh [platform ...]
#
# With no platform arguments it does all 13: tasks auction ehail notify farms finance emall billing hr
# tutor design pulse plug. (Analytics deploys differently: Docker/own host; not included.)
#
# DEPLOY_KNOWN_HOSTS is read from the host with ssh-keyscan -- verify the fingerprints match your
# cPanel account before trusting it (ssh-keygen -lf <(ssh-keyscan -t ed25519 $DEPLOY_HOST)).
# DEPLOY_PATH is set to ~/<key>.infodot.co.za/<key> (the layout in each docs/deployment.md).
set -euo pipefail
: "${DEPLOY_HOST:?set DEPLOY_HOST}"; : "${DEPLOY_USER:?set DEPLOY_USER}"; : "${DEPLOY_KEY_FILE:?set DEPLOY_KEY_FILE}"
KEY_FILE="${DEPLOY_KEY_FILE/#\~/$HOME}"
[ -r "$KEY_FILE" ] || { echo "cannot read $KEY_FILE" >&2; exit 1; }
PORT="${DEPLOY_PORT:-22}"
PLATFORMS=("$@"); [ ${#PLATFORMS[@]} -gt 0 ] || PLATFORMS=(tasks auction ehail notify farms finance emall billing hr tutor design pulse plug)
cap() { case "$1" in hr) echo HR;; *) echo "$(tr '[:lower:]' '[:upper:]' <<<"${1:0:1}")${1:1}";; esac; }
KNOWN="$(ssh-keyscan -p "$PORT" -t ed25519,rsa "$DEPLOY_HOST" 2>/dev/null)"
[ -n "$KNOWN" ] || { echo "ssh-keyscan returned nothing for $DEPLOY_HOST" >&2; exit 1; }
HOME_DIR="${DEPLOY_HOME:-/home/$DEPLOY_USER}"
for k in "${PLATFORMS[@]}"; do
  R="sakhilebhayi/Dot.$(cap "$k")"
  gh secret set DEPLOY_HOST        --repo "$R" --body "$DEPLOY_HOST"
  gh secret set DEPLOY_USER        --repo "$R" --body "$DEPLOY_USER"
  gh secret set DEPLOY_SSH_KEY     --repo "$R" < "$KEY_FILE"
  gh secret set DEPLOY_KNOWN_HOSTS --repo "$R" --body "$KNOWN"
  gh secret set DEPLOY_PATH        --repo "$R" --body "$HOME_DIR/$k.infodot.co.za/$k"
  echo "set DEPLOY_HOST DEPLOY_USER DEPLOY_SSH_KEY DEPLOY_KNOWN_HOSTS DEPLOY_PATH on $R"
done
