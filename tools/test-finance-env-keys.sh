#!/usr/bin/env bash
# TEMPORARY (added 2026-11-13): delete together with tools/finance-env-keys.sh after the first deploy that ran it.
# Tests tools/finance-env-keys.sh against a throwaway app folder, the way deploy.yml runs it on the host. No value may ever appear on screen.
# shellcheck disable=SC2034  # OUT, OUT2 and RC are read inside the check strings, which shellcheck cannot see into
set -uo pipefail
HERE="$(cd "$(dirname "$0")" && pwd)"; S="$HERE/finance-env-keys.sh"
T="$(mktemp -d)"; trap 'rm -rf "$T"' EXIT
FAILS=0; N=0
check() { N=$((N+1)); if eval "$2"; then echo "ok   - $1"; else echo "FAIL - $1"; FAILS=$((FAILS+1)); fi; }
val() { grep -E "^$2=" "$1/.env" | tail -1 | cut -d= -f2-; }
count() { grep -c -E "^$2=" "$1/.env" || true; }
backups() { find "$1" -maxdepth 1 -name '.env.bak-finance-keys-*' | wc -l | tr -d ' '; }
mode() { stat -c '%a' "$1" 2>/dev/null || stat -f '%Lp' "$1"; }
# Generated at run time: this repository is public and holds no secret, not even a made-up one that looks real.
SECRET="$(head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n')"
CALLBACK="$(head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n')"

# The host as it is today: the old names, among other settings, values with "=", "#", spaces and quotes in them.
host() {
  rm -rf "$T/app"; mkdir -p "$T/app"
  {
    echo 'APP_NAME=InfoDot'
    echo 'APP_DEBUG=false'
    echo '# payments'
    echo "BILLING_ENTITLEMENT_SECRET=$SECRET"
    echo 'LICENCE_BILLING_URL=https://pay.example.test/base?a=b=c'
    echo 'LICENCE_BILLING_CONNECTED=true'
    echo ''
    echo 'ECOSYSTEM_SECRET_FINANCE=keep-me'
    echo 'MAIL_FROM_NAME="Info Dot #1"'
    echo 'NOT_LICENCE_BILLING_URL=untouched'
    echo 'LICENCE_BILLING_URL_EXTRA=untouched'
    printf 'LAST_LINE=no-trailing-newline'
  } > "$T/app/.env"
  chmod 640 "$T/app/.env"
}

host; cp "$T/app/.env" "$T/before"
OUT="$(bash "$S" "$T/app" 2>&1)"; RC=$?
check "the run succeeds" "[ $RC -eq 0 ]"
check "the three old names present are renamed, values byte for byte" "[ \"\$(val $T/app FINANCE_ENTITLEMENT_SECRET)\" = \"$SECRET\" ] && [ \"\$(val $T/app LICENCE_FINANCE_URL)\" = 'https://pay.example.test/base?a=b=c' ] && [ \"\$(val $T/app LICENCE_FINANCE_CONNECTED)\" = true ]"
check "the old names are gone" "[ \"\$(count $T/app BILLING_ENTITLEMENT_SECRET)\" = 0 ] && [ \"\$(count $T/app LICENCE_BILLING_URL)\" = 0 ] && [ \"\$(count $T/app LICENCE_BILLING_CONNECTED)\" = 0 ]"
check "a name that was never set is not invented" "[ \"\$(count $T/app LICENCE_FINANCE_PLATFORM)\" = 0 ] && [ \"\$(count $T/app SEMINARS_FINANCE_CALLBACK_SECRET)\" = 0 ] && [ \"\$(count $T/app LICENCE_FINANCE_FAILURE_BACKOFF)\" = 0 ]"
check "names that only contain an old name are untouched" "[ \"\$(val $T/app NOT_LICENCE_BILLING_URL)\" = untouched ] && [ \"\$(val $T/app LICENCE_BILLING_URL_EXTRA)\" = untouched ]"
check "every other line is exactly as it was, in the same order" "diff <(sed -E 's/^(BILLING_ENTITLEMENT_SECRET|LICENCE_BILLING_URL|LICENCE_BILLING_CONNECTED)=/X=/' $T/before) <(sed -E 's/^(FINANCE_ENTITLEMENT_SECRET|LICENCE_FINANCE_URL|LICENCE_FINANCE_CONNECTED)=/X=/' $T/app/.env) >/dev/null"
check "the missing trailing newline is still missing (nothing appended)" "[ \"\$(tail -c 1 $T/app/.env)\" = e ] && [ \"\$(wc -c < $T/app/.env)\" = \"\$(wc -c < $T/before)\" ]"
check "one backup beside .env, identical to the original, mode 600" "[ \"\$(backups $T/app)\" = 1 ] && cmp -s $T/before $T/app/.env.bak-finance-keys-* && [ \"\$(mode $T/app/.env.bak-finance-keys-*)\" = 600 ]"
check ".env keeps its own mode" "[ \"\$(mode $T/app/.env)\" = 640 ]"
check "no temporary file is left behind" "[ -z \"\$(find $T/app -name '.env.tmp.*')\" ]"
check "only names are printed: no value appears" "! printf '%s' \"\$OUT\" | grep -qF -e \"$SECRET\" -e 'pay.example.test' -e 'keep-me'"
check "the names that changed are printed" "printf '%s' \"\$OUT\" | grep -q 'renamed BILLING_ENTITLEMENT_SECRET to FINANCE_ENTITLEMENT_SECRET' && printf '%s' \"\$OUT\" | grep -q 'renamed LICENCE_BILLING_URL to LICENCE_FINANCE_URL'"

cp "$T/app/.env" "$T/after"
OUT2="$(bash "$S" "$T/app" 2>&1)"; RC=$?
check "a second run succeeds, changes nothing and makes no second backup" "[ $RC -eq 0 ] && cmp -s $T/after $T/app/.env && [ \"\$(backups $T/app)\" = 1 ] && printf '%s' \"\$OUT2\" | grep -q 'nothing changed'"

# All six old names, one of them twice.
host
{ echo; echo 'LICENCE_BILLING_PLATFORM=infodot'; echo 'LICENCE_BILLING_FAILURE_BACKOFF=45'; echo "SEMINARS_BILLING_CALLBACK_SECRET=$CALLBACK"; echo 'LICENCE_BILLING_CONNECTED=false'; } >> "$T/app/.env"
OUT="$(bash "$S" "$T/app" 2>&1)"
check "all six names are renamed" "[ \"\$(val $T/app LICENCE_FINANCE_PLATFORM)\" = infodot ] && [ \"\$(val $T/app LICENCE_FINANCE_FAILURE_BACKOFF)\" = 45 ] && [ \"\$(val $T/app SEMINARS_FINANCE_CALLBACK_SECRET)\" = \"$CALLBACK\" ] && ! grep -qE '^(BILLING_ENTITLEMENT_SECRET|LICENCE_BILLING_(URL|CONNECTED|PLATFORM|FAILURE_BACKOFF)|SEMINARS_BILLING_CALLBACK_SECRET)=' $T/app/.env"
check "a name set twice is renamed on both lines, so the last one still wins" "[ \"\$(count $T/app LICENCE_FINANCE_CONNECTED)\" = 2 ] && [ \"\$(val $T/app LICENCE_FINANCE_CONNECTED)\" = false ]"
check "and still no value is printed" "! printf '%s' \"\$OUT\" | grep -qF -e \"$SECRET\" -e \"$CALLBACK\""

# The new name is already there: the owner set it by hand, so its value must win and the old line is not touched.
host
{ echo; echo 'LICENCE_FINANCE_URL=https://already.example.test'; } >> "$T/app/.env"
OUT="$(bash "$S" "$T/app" 2>&1)"
check "a new name that is already set is kept and its old line is left alone" "[ \"\$(val $T/app LICENCE_FINANCE_URL)\" = https://already.example.test ] && [ \"\$(count $T/app LICENCE_FINANCE_URL)\" = 1 ] && [ \"\$(count $T/app LICENCE_BILLING_URL)\" = 1 ] && printf '%s' \"\$OUT\" | grep -q 'LICENCE_FINANCE_URL is already set'"
check "while the other old names are still renamed" "[ \"\$(val $T/app FINANCE_ENTITLEMENT_SECRET)\" = \"$SECRET\" ] && [ \"\$(count $T/app BILLING_ENTITLEMENT_SECRET)\" = 0 ]"

# A host that never had the settings.
rm -rf "$T/app"; mkdir -p "$T/app"; printf 'APP_NAME=InfoDot\nSEMINARS_PAID_ENABLED=false\n' > "$T/app/.env"; cp "$T/app/.env" "$T/before"
OUT="$(bash "$S" "$T/app" 2>&1)"; RC=$?
check "a .env without any of the names is left byte for byte, with no backup" "[ $RC -eq 0 ] && cmp -s $T/before $T/app/.env && [ \"\$(backups $T/app)\" = 0 ]"

# Refusals.
check "a folder without .env is refused" "! bash $S $T/nope >/dev/null 2>&1"
check "no argument is refused" "! bash $S >/dev/null 2>&1"
host; chmod 440 "$T/app/.env"; cp "$T/app/.env" "$T/before"
if [ "$(id -u)" != 0 ]; then
  check "a read-only .env is refused, untouched, with no backup" "! bash $S $T/app >/dev/null 2>&1 && cmp -s $T/before $T/app/.env && [ \"\$(backups $T/app)\" = 0 ]"
fi
chmod 640 "$T/app/.env"

# The settings the application reads afterwards are the ones that were on the host: the config cache is built from the renamed file.
host; bash "$S" "$T/app" >/dev/null 2>&1
check "every name the code reads is a name the script writes" "for k in FINANCE_ENTITLEMENT_SECRET LICENCE_FINANCE_URL LICENCE_FINANCE_CONNECTED LICENCE_FINANCE_PLATFORM LICENCE_FINANCE_FAILURE_BACKOFF; do grep -q \"env('\$k'\" $HERE/../config/licensing.php || exit 1; done; grep -q \"env('SEMINARS_FINANCE_CALLBACK_SECRET')\" $HERE/../config/seminars.php"
check "and no config file still reads an old name" "! grep -rqE \"env\\('(BILLING_ENTITLEMENT_SECRET|LICENCE_BILLING_[A-Z_]+|SEMINARS_BILLING_CALLBACK_SECRET)'\" $HERE/../config"

echo; echo "$N checks, $FAILS failed"; [ "$FAILS" = 0 ]
