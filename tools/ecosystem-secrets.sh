#!/usr/bin/env bash
# Generates every shared secret the Dot platforms need to talk to each other and writes them to ONE
# local file (mode 600), grouped by "paste this into that app's .env". Nothing is printed to the
# terminal except the file path, and nothing is committed: keep the file outside the repository
# (default: ~/dot-ecosystem-secrets.env) or in a gitignored path.
#
#   tools/ecosystem-secrets.sh [output-file]
#
# Re-running never overwrites an existing file: delete it first if you want fresh secrets (and then
# redeploy BOTH sides of every pair, otherwise sign-in/ingest calls start failing with 401).
set -euo pipefail

OUT="${1:-$HOME/dot-ecosystem-secrets.env}"
if [ -e "$OUT" ]; then echo "Refusing to overwrite $OUT (delete it first)." >&2; exit 1; fi
command -v openssl >/dev/null || { echo "openssl is required" >&2; exit 1; }

secret() { openssl rand -hex 32; }
upper() { echo "$1" | tr '[:lower:]' '[:upper:]'; }

SIGNIN="analytics auction billing design ehail emall farms finance hr notify plug pulse tasks tutor"
NOTIFY_CALLERS="auction billing ehail farms hr tasks"
ANALYTICS_PUBLISHERS="auction billing design ehail emall farms hr notify pulse tasks tutor"
BILLING_ENTITLEMENT_CALLERS="analytics auction ehail emall farms hr notify tasks tutor"   # optional: apps that ask Billing "what plan is this company on?"

umask 077
{
  echo "# Dot ecosystem shared secrets -- generated $(date -u +%Y-%m-%dT%H:%MZ). KEEP PRIVATE. Never commit."
  echo "# Each block says where the SAME value goes on both sides of a pair."
  echo

  echo "########## 1. Signed sign-in (InfoDot <-> each app) ##########"
  echo "# InfoDot .env gets ECOSYSTEM_SECRET_<KEY>; the app gets INFODOT_SERVICE_SECRET (same value) + INFODOT_PLATFORM_KEY."
  for k in $SIGNIN; do
    v=$(secret)
    echo "# --- $k"
    echo "InfoDot:    ECOSYSTEM_SECRET_$(upper $k)=$v"
    echo "Dot.$k:     INFODOT_SERVICE_SECRET=$v"
    echo "Dot.$k:     INFODOT_PLATFORM_KEY=$k"
  done
  echo

  echo "########## 2. Notify ingest (app -> Dot.Notify) ##########"
  echo "# Dot.Notify .env gets NOTIFY_INGEST_SECRET_<KEY>; the calling app gets NOTIFY_INGEST_SECRET (same value),"
  echo "# NOTIFY_INGEST_URL=https://notify.infodot.co.za/api/ingest/events and NOTIFY_PLATFORM_KEY=<key>."
  for k in $NOTIFY_CALLERS; do
    v=$(secret)
    echo "# --- $k"
    echo "Dot.notify: NOTIFY_INGEST_SECRET_$(upper $k)=$v"
    echo "Dot.$k:     NOTIFY_INGEST_SECRET=$v"
    echo "Dot.$k:     NOTIFY_PLATFORM_KEY=$k"
  done
  echo

  echo "########## 3. Analytics ingest (app -> Dot.Analytics) ##########"
  echo "# Dot.Analytics .env gets ANALYTICS_INGEST_SECRET_<KEY>; the publishing app gets ANALYTICS_INGEST_SECRET (same value),"
  echo "# ANALYTICS_INGEST_URL=https://analytics.infodot.co.za/api/ingest/snapshots and ANALYTICS_PLATFORM_KEY=<key>."
  echo "# Apps that publish platform-wide numbers (pulse, design) also need ANALYTICS_COMPANY_UUID = the InfoDot company uuid they report under."
  for k in $ANALYTICS_PUBLISHERS; do
    v=$(secret)
    echo "# --- $k"
    echo "Dot.analytics: ANALYTICS_INGEST_SECRET_$(upper $k)=$v"
    echo "Dot.$k:        ANALYTICS_INGEST_SECRET=$v"
    echo "Dot.$k:        ANALYTICS_PLATFORM_KEY=$k"
  done
  echo

  echo "########## 4. Billing entitlements (optional: app -> Dot.Billing) ##########"
  for k in $BILLING_ENTITLEMENT_CALLERS; do
    echo "Dot.billing: BILLING_ENTITLEMENT_SECRET_$(upper $k)=$(secret)    # and the same value in Dot.$k wherever it calls the entitlements API"
  done
} > "$OUT"
chmod 600 "$OUT"
echo "Wrote $OUT (mode 600). Open it locally; do not paste it into chat, tickets or commits."
