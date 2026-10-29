#!/usr/bin/env bash
# Tests tools/r13-enable.sh against throwaway .env files. No secret may ever appear on screen.
set -uo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"; S="$HERE/r13-enable.sh"
T="$(mktemp -d)"; trap 'rm -rf "$T"' EXIT
FAILS=0; N=0
check() { N=$((N+1)); if eval "$2"; then echo "ok   - $1"; else echo "FAIL - $1"; FAILS=$((FAILS+1)); fi; }
val() { grep -E "^$2=" "$1/.env" | tail -1 | cut -d= -f2-; }
fresh() { rm -rf "$T/i" "$T/b"; mkdir -p "$T/i" "$T/b"; printf 'APP_NAME=InfoDot\nREPUTATION_ENABLED=false\nOTHER=keep\n' > "$T/i/.env"; printf 'APP_NAME=Billing\nBILLING_ENTITLEMENT_SECRET_INFODOT=\nBILLING_MARKETPLACE_ENABLED=false\nOTHER=keep' > "$T/b/.env"; }

fresh
OUT="$("$S" --infodot "$T/i" --billing "$T/b" --stage reputation --stage billing-link --stage paid-seminars 2>&1)"
check "dry run changes nothing" "[ \"\$(val $T/i REPUTATION_ENABLED)\" = false ] && [ -z \"\$(val $T/b BILLING_ENTITLEMENT_SECRET_INFODOT)\" ]"
check "dry run lists key names" "printf '%s' \"\$OUT\" | grep -q 'REPUTATION_ENABLED'"
check "dry run makes no backup" "[ -z \"\$(ls $T/i/.env.bak-r13-* 2>/dev/null)\" ]"

OUT="$("$S" --infodot "$T/i" --billing "$T/b" --stage reputation --stage billing-link --stage paid-seminars --apply 2>&1)"
check "reputation on" "[ \"\$(val $T/i REPUTATION_ENABLED)\" = true ]"
A1="$(val "$T/b" BILLING_ENTITLEMENT_SECRET_INFODOT)"; A2="$(val "$T/i" BILLING_ENTITLEMENT_SECRET)"
check "the entitlement secret is the same in both apps and 64 hex characters" "[ -n \"$A1\" ] && [ \"$A1\" = \"$A2\" ] && [ \${#A1} -eq 64 ] && printf '%s' \"$A1\" | grep -Eq '^[0-9a-f]{64}$'"
B1="$(val "$T/b" BILLING_MARKETPLACE_CALLBACK_SECRET)"; B2="$(val "$T/i" SEMINARS_BILLING_CALLBACK_SECRET)"
check "the callback secret is the same in both apps, and different from the first" "[ -n \"$B1\" ] && [ \"$B1\" = \"$B2\" ] && [ \"$B1\" != \"$A1\" ]"
check "no secret was printed" "! printf '%s' \"\$OUT\" | grep -qF -e \"$A1\" -e \"$B1\""
check "billing link keys" "[ \"\$(val $T/i LICENCE_BILLING_CONNECTED)\" = true ] && [ \"\$(val $T/i LICENCE_BILLING_URL)\" = https://billing.infodot.co.za ]"
check "marketplace keys" "[ \"\$(val $T/b BILLING_MARKETPLACE_ENABLED)\" = true ] && [ \"\$(val $T/i SEMINARS_PAID_ENABLED)\" = true ] && [ \"\$(val $T/b BILLING_MARKETPLACE_CALLBACK_URL)\" = https://infodot.co.za/api/billing/ticket-events ] && [ \"\$(val $T/b BILLING_MARKETPLACE_RETURN_HOSTS)\" = infodot.co.za ] && [ \"\$(val $T/b BILLING_MARKETPLACE_PLATFORM_PERCENT)\" = 10 ]"
check "other keys untouched, a file with no trailing newline still parses" "[ \"\$(val $T/i OTHER)\" = keep ] && [ \"\$(val $T/b OTHER)\" = keep ] && [ \"\$(val $T/b BILLING_MARKETPLACE_ENABLED)\" = true ]"
check "a backup was made of each .env" "ls $T/i/.env.bak-r13-* >/dev/null 2>&1 && ls $T/b/.env.bak-r13-* >/dev/null 2>&1"

OUT2="$("$S" --infodot "$T/i" --billing "$T/b" --stage reputation --stage billing-link --stage paid-seminars --apply 2>&1)"
check "running again changes nothing and keeps the same secrets" "printf '%s' \"\$OUT2\" | grep -q 'Nothing to change' && [ \"\$(val $T/i BILLING_ENTITLEMENT_SECRET)\" = \"$A1\" ] && [ \"\$(val $T/i SEMINARS_BILLING_CALLBACK_SECRET)\" = \"$B1\" ]"

fresh
"$S" --infodot "$T/i" --stage reputation --apply >/dev/null 2>&1
check "reputation alone needs no Billing folder and touches only its key" "[ \"\$(val $T/i REPUTATION_ENABLED)\" = true ] && ! grep -q BILLING $T/i/.env"

fresh
check "paid-seminars without billing-link is refused" "! $S --infodot $T/i --billing $T/b --stage paid-seminars --apply >/dev/null 2>&1"
check "a missing .env is refused" "! $S --infodot $T/nope --stage reputation >/dev/null 2>&1"
check "an unknown stage is refused" "! $S --infodot $T/i --stage everything >/dev/null 2>&1"
check "a non-https URL is refused" "! $S --infodot $T/i --billing $T/b --stage billing-link --billing-url http://x.test >/dev/null 2>&1"

fresh
"$S" --infodot "$T/i" --billing "$T/b" --stage billing-link --stage paid-seminars --issuer-name "InfoDot (Pty) Ltd" --issuer-address "1 Test St, Cape Town" --issuer-vat 4123456789 --apply >/dev/null 2>&1
check "issuer details are written when all three are given" "grep -q '^SEMINARS_ISSUER_VAT_NUMBER=4123456789' $T/i/.env && grep -q '^SEMINARS_ISSUER_NAME=\"InfoDot (Pty) Ltd\"' $T/i/.env"
fresh
check "partial issuer details are refused" "! $S --infodot $T/i --billing $T/b --stage billing-link --stage paid-seminars --issuer-name X --apply >/dev/null 2>&1"

fresh
LK="AKtestkey123"; LS="LKsecret-for-the-test-0123456789abcdef"
OUTV="$(LIVEKIT_API_KEY=$LK LIVEKIT_API_SECRET=$LS "$S" --infodot "$T/i" --stage video --livekit-url wss://live.example.org --apply 2>&1)"
check "video stage writes the four keys" "[ \"\$(val $T/i LIVEKIT_URL)\" = wss://live.example.org ] && [ \"\$(val $T/i LIVEKIT_API_KEY)\" = $LK ] && [ \"\$(val $T/i LIVEKIT_API_SECRET)\" = $LS ] && [ \"\$(val $T/i SEMINARS_VIDEO_ENABLED)\" = true ]"
check "video stage never prints the key pair" "! printf '%s' \"\$OUTV\" | grep -qF -e $LK -e $LS"
check "video stage refuses a missing key pair" "! env -u LIVEKIT_API_KEY -u LIVEKIT_API_SECRET $S --infodot $T/i --stage video --livekit-url wss://live.example.org >/dev/null 2>&1"
check "video stage refuses a non-wss URL" "! LIVEKIT_API_KEY=$LK LIVEKIT_API_SECRET=$LS $S --infodot $T/i --stage video --livekit-url https://live.example.org >/dev/null 2>&1"

fresh
"$S" --infodot "$T/i" --stage p2p --apply >/dev/null 2>&1
check "p2p stage turns the rooms on and leaves the STUN default alone" "[ \"\$(val $T/i SEMINARS_P2P_ENABLED)\" = true ] && ! grep -q P2P_STUN_URLS $T/i/.env"
"$S" --infodot "$T/i" --stage p2p --stun-urls "stun:stun.example.org:3478,stun:b.example.org:3478" --apply >/dev/null 2>&1
check "p2p stage can set your own STUN servers" "[ \"\$(val $T/i P2P_STUN_URLS)\" = stun:stun.example.org:3478,stun:b.example.org:3478 ]"
check "p2p stage refuses a bad STUN list" "! $S --infodot $T/i --stage p2p --stun-urls 'http://x' --apply >/dev/null 2>&1"

echo; echo "$N checks, $FAILS failed"; [ "$FAILS" = 0 ]
