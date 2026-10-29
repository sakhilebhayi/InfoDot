#!/usr/bin/env bash
# Switches on the R-13 features in the .env files of InfoDot and Dot.Billing, on the host, by YOU running it.
# It never prints a secret, never changes a key you did not ask for, and backs up each .env first.
#
#   tools/r13-enable.sh --infodot /path/to/infodot --billing /path/to/billing --stage reputation [--stage billing-link] [--stage paid-seminars] [--apply]
#
# Without --apply it only says which keys WOULD change (names only). Stages:
#   reputation       InfoDot: REPUTATION_ENABLED=true
#   billing-link     InfoDot reads seat counts from Billing. Creates ONE shared secret (or reuses the one already in Billing's
#                    BILLING_ENTITLEMENT_SECRET_INFODOT) and writes it to both apps.
#   paid-seminars    Paid tickets (Paystack through Billing). Needs billing-link. Creates ONE shared callback secret and writes it to both apps.
#                    Do this only after the Paystack test-mode run and your adviser's sign-off (docs/programme/R13_GO_LIVE.md).
#   video            InfoDot seminar rooms on your LiveKit server. Needs --livekit-url wss://... and the key pair in the environment
#                    (LIVEKIT_API_KEY and LIVEKIT_API_SECRET, typed with `read -rs` so they never appear in a command line). See
#                    docs/programme/R13_LIVEKIT_SERVER.md; do this only after the server's acceptance test passes.
# Optional: --billing-url https://billing.infodot.co.za  --infodot-url https://infodot.co.za
#           --issuer-name "..." --issuer-address "..." --issuer-vat 4123456789   (InfoDot's own details for commission invoices)
#           --cache   run `php artisan config:cache` in each app afterwards.
set -euo pipefail

INFODOT=""; BILLING=""; APPLY=0; CACHE=0; STAGES=()
BILLING_URL="https://billing.infodot.co.za"; INFODOT_URL="https://infodot.co.za"
ISSUER_NAME=""; ISSUER_ADDRESS=""; ISSUER_VAT=""; LIVEKIT_URL=""

while [ $# -gt 0 ]; do
  case "$1" in
    --infodot) INFODOT="$2"; shift 2 ;;
    --billing) BILLING="$2"; shift 2 ;;
    --stage) STAGES+=("$2"); shift 2 ;;
    --billing-url) BILLING_URL="$2"; shift 2 ;;
    --infodot-url) INFODOT_URL="$2"; shift 2 ;;
    --livekit-url) LIVEKIT_URL="$2"; shift 2 ;;
    --issuer-name) ISSUER_NAME="$2"; shift 2 ;;
    --issuer-address) ISSUER_ADDRESS="$2"; shift 2 ;;
    --issuer-vat) ISSUER_VAT="$2"; shift 2 ;;
    --apply) APPLY=1; shift ;;
    --cache) CACHE=1; shift ;;
    *) echo "unknown option: $1" >&2; exit 2 ;;
  esac
done

die() { echo "error: $*" >&2; exit 1; }
[ "${#STAGES[@]}" -gt 0 ] || die "give at least one --stage (reputation, billing-link, paid-seminars)"
has_stage() { local s; for s in "${STAGES[@]}"; do [ "$s" = "$1" ] && return 0; done; return 1; }
for s in "${STAGES[@]}"; do case "$s" in reputation|billing-link|paid-seminars|video) ;; *) die "unknown stage: $s" ;; esac; done
[ -n "$INFODOT" ] && [ -f "$INFODOT/.env" ] || die "--infodot must be a folder that holds .env"
if has_stage billing-link || has_stage paid-seminars; then
  [ -n "$BILLING" ] && [ -f "$BILLING/.env" ] || die "--billing must be a folder that holds .env"
fi
has_stage paid-seminars && ! has_stage billing-link && ! grep -q '^LICENCE_BILLING_CONNECTED=true' "$INFODOT/.env" && die "paid-seminars needs billing-link first (run both stages)"
if has_stage video; then
  [[ "$LIVEKIT_URL" =~ ^wss://[A-Za-z0-9.-]+(:[0-9]+)?$ ]] || die "--livekit-url must look like wss://live.example.org"
  [ -n "${LIVEKIT_API_KEY:-}" ] && [ -n "${LIVEKIT_API_SECRET:-}" ] || die "set LIVEKIT_API_KEY and LIVEKIT_API_SECRET in your shell first (use read -rs, see docs/programme/R13_LIVEKIT_SERVER.md)"
fi
case "$BILLING_URL$INFODOT_URL" in *https://*https://*) ;; *) die "both URLs must be https" ;; esac

CHANGED=()   # key names only, never values
get_env() { grep -E "^$2=" "$1/.env" | tail -1 | cut -d= -f2- | tr -d "\"' " || true; }

# set_env <dir> <KEY> <value>: replace the key if present, else append. Value is never echoed.
set_env() {
  local dir="$1" key="$2" value="$3" cur
  cur="$(get_env "$dir" "$key")"
  [ "$cur" = "$value" ] && return 0
  CHANGED+=("$(basename "$dir"):$key")
  [ "$APPLY" = 1 ] || return 0
  local tmp; tmp="$(mktemp)"
  if grep -qE "^$key=" "$dir/.env"; then
    awk -v k="$key" -v v="$value" 'BEGIN{FS=OFS="="} $1==k {print k "=" v; next} {print}' "$dir/.env" > "$tmp"
  else
    { cat "$dir/.env"; [ -n "$(tail -c1 "$dir/.env")" ] && echo; echo "$key=$value"; } > "$tmp"
  fi
  cat "$tmp" > "$dir/.env"; rm -f "$tmp"
}

new_secret() { head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n'; }

backup() {
  [ "$APPLY" = 1 ] || return 0
  cp -p "$1/.env" "$1/.env.bak-r13-$(date +%Y%m%d%H%M%S)"
}
backup "$INFODOT"; { has_stage billing-link || has_stage paid-seminars; } && backup "$BILLING"

if has_stage reputation; then
  set_env "$INFODOT" REPUTATION_ENABLED true
fi

if has_stage billing-link; then
  A="$(get_env "$BILLING" BILLING_ENTITLEMENT_SECRET_INFODOT)"
  [ -n "$A" ] || A="$(new_secret)"
  set_env "$BILLING" BILLING_ENTITLEMENT_SECRET_INFODOT "$A"
  set_env "$INFODOT" BILLING_ENTITLEMENT_SECRET "$A"
  set_env "$INFODOT" LICENCE_BILLING_URL "$BILLING_URL"
  set_env "$INFODOT" LICENCE_BILLING_CONNECTED true
  A=""
fi

if has_stage paid-seminars; then
  [ -n "$(get_env "$BILLING" BILLING_ENTITLEMENT_SECRET_INFODOT)" ] || [ "$APPLY" = 0 ] || die "Billing has no BILLING_ENTITLEMENT_SECRET_INFODOT; run billing-link first"
  B="$(get_env "$BILLING" BILLING_MARKETPLACE_CALLBACK_SECRET)"
  [ -n "$B" ] || B="$(new_secret)"
  set_env "$BILLING" BILLING_MARKETPLACE_CALLBACK_SECRET "$B"
  set_env "$INFODOT" SEMINARS_BILLING_CALLBACK_SECRET "$B"
  B=""
  set_env "$BILLING" BILLING_MARKETPLACE_PLATFORM_PERCENT 10
  set_env "$BILLING" BILLING_MARKETPLACE_CALLBACK_URL "${INFODOT_URL%/}/api/billing/ticket-events"
  set_env "$BILLING" BILLING_MARKETPLACE_RETURN_HOSTS "$(printf '%s' "$INFODOT_URL" | sed -E 's#^https://##; s#/.*$##')"
  set_env "$BILLING" BILLING_MARKETPLACE_ENABLED true
  set_env "$INFODOT" SEMINARS_PAID_ENABLED true
  if [ -n "$ISSUER_NAME$ISSUER_ADDRESS$ISSUER_VAT" ]; then
    [ -n "$ISSUER_NAME" ] && [ -n "$ISSUER_ADDRESS" ] && [[ "$ISSUER_VAT" =~ ^[0-9]{10}$ ]] || die "give all of --issuer-name, --issuer-address and a 10-digit --issuer-vat"
    set_env "$INFODOT" SEMINARS_ISSUER_NAME "\"$ISSUER_NAME\""
    set_env "$INFODOT" SEMINARS_ISSUER_ADDRESS "\"$ISSUER_ADDRESS\""
    set_env "$INFODOT" SEMINARS_ISSUER_VAT_NUMBER "$ISSUER_VAT"
  fi
fi

if has_stage video; then
  set_env "$INFODOT" LIVEKIT_URL "$LIVEKIT_URL"
  set_env "$INFODOT" LIVEKIT_API_KEY "$LIVEKIT_API_KEY"
  set_env "$INFODOT" LIVEKIT_API_SECRET "$LIVEKIT_API_SECRET"
  set_env "$INFODOT" SEMINARS_VIDEO_ENABLED true
fi

if [ "${#CHANGED[@]}" -eq 0 ]; then echo "Nothing to change: the requested stages are already set."; exit 0; fi
if [ "$APPLY" = 1 ]; then echo "Set (names only):"; else echo "Would set (names only; add --apply to do it):"; fi
printf '  %s\n' "${CHANGED[@]}"

if [ "$APPLY" = 1 ] && [ "$CACHE" = 1 ]; then
  for d in "$INFODOT" ${BILLING:+"$BILLING"}; do (cd "$d" && php artisan config:cache >/dev/null && echo "config cached: $(basename "$d")"); done
elif [ "$APPLY" = 1 ]; then
  echo "Now run 'php artisan config:cache' in each app (or re-run the deploy) so the new values are read."
fi
