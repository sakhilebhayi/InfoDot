#!/usr/bin/env bash
# Fake-host test of the App Folder Guard docroot mode: tools/docroot-guard-host.sh (the very script the workflow pipes to
# the host), tools/probe-exposure.sh (retry timeline), tools/site-status.sh and tools/docroot-guard-summary.sh.
# No network, no SSH: HOME is a temp dir and `curl` is a shim. Runs in CI (Linux) and locally (macOS bash 3.2 ok).
# It cannot show how LiteSpeed applies the rule: that needs the host (see the PR / SECURITY_ASSESSMENT.md S-2d).
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
W="$(mktemp -d)"; trap 'rm -rf "$W"' EXIT
export HOME="$W/home"; mkdir -p "$HOME"
HOST="$ROOT/tools/docroot-guard-host.sh"
FAIL=0; N=0
ok() { N=$((N + 1)); echo "ok   - $1"; }
bad() { N=$((N + 1)); echo "FAIL - $1"; FAIL=1; }
check() { if eval "$2"; then ok "$1"; else bad "$1"; fi; }
same() { if cmp -s "$2" "$3"; then ok "$1"; else bad "$1"; echo "      differs: $2 vs $3"; fi; }

# ---- the exact text the block must have (typed here independently of the script) ----
expected_block() { # $1 = key, $2 = eol ("" or "\r")
  local k="$1" e="${2:-}"
  printf '# BEGIN dot-app-folder-guard %s%b\n' "$k" "$e"
  printf '<IfModule mod_rewrite.c>%b\n' "$e"
  printf 'RewriteEngine On%b\n' "$e"
  printf 'RewriteRule ^%s/(\\.env.*|\\.git.*|artisan|composer\\.(json|lock)|package(-lock)?\\.json|phpunit\\.xml.*|database/|storage/|vendor/|bootstrap/|config/|tests/|node_modules/) - [F,L,NC]%b\n' "$k" "$e"
  printf '</IfModule>%b\n' "$e"
  printf '# END dot-app-folder-guard %s%b\n' "$k" "$e"
}
OTHER_RULES() { printf '%s\n' '# Dot: docroot router' 'RewriteEngine On' 'RewriteCond %{REQUEST_URI} !^/build/' 'RewriteRule ^(.*)$ app/public/$1 [L]' '<Files ".env">' 'Require all denied' '</Files>'; }

docroot() { echo "$HOME/$1.infodot.co.za"; }
mk() { # $1 = host, $2 = key ; docroot with a Laravel app marker
  mkdir -p "$(docroot "$1")/$2"; : > "$(docroot "$1")/$2/artisan"; }
run() { bash "$HOST" "$@" 2>&1; }   # output of the host script
reset() { rm -rf "$HOME"; mkdir -p "$HOME"; }

# ---------- 1. input validation: nothing is touched on bad input ----------
reset; mk pres press
for badpair in 'pres:Press' 'pres:pre ss' 'pres:../x' 'pres:p;ls' 'Pres:press' 'pres' ':press' 'pres:' 'pres:press:x' 'pres:pre$ss' 'pres:a|b' 'pres:a.b' 'pres:a/b'; do
  out="$(bash "$HOST" write "$badpair" 2>&1)"; rc=$?
  check "bad pair [$badpair] is rejected (exit 2)" "[ $rc -eq 2 ]"
done
out="$(bash "$HOST" write pres:press 'pres:bad key' 2>&1)"; rc=$?
check "one bad pair among good ones rejects the whole call before any write" "[ $rc -eq 2 ] && [ ! -e '$(docroot pres)/.htaccess' ]"
bash "$HOST" frobnicate pres:press >/dev/null 2>&1; check "unknown action is rejected" "[ \$? -eq 2 ]"
bash "$HOST" write >/dev/null 2>&1; check "no pair is rejected" "[ \$? -eq 2 ]"
check "no .htaccess and no state dir appeared after the rejects" "[ ! -e '$(docroot pres)/.htaccess' ] && [ ! -e '$HOME/.dot-docroot-guard' ]"

# ---------- 2. file missing: created with only the block ----------
reset; mk pres press; D="$(docroot pres)"
out="$(run write pres:press)"; rc=$?
check "missing file: exit 0, 'written (new file)'" "[ $rc -eq 0 ] && printf '%s' '$out' | grep -q '^pres:press: written (new file) backup=none\$'"
expected_block press > "$W/exp"; same "missing file: the file is exactly the block, nothing else" "$D/.htaccess" "$W/exp"
check "missing file: no backup invented" "[ ! -e '$D/.htaccess.before-docroot-guard' ]"
check "the output never contains rule text" "! printf '%s' '$out' | grep -q RewriteRule"
check "the rule line is exactly the specified one" "grep -Fx 'RewriteRule ^press/(\\.env.*|\\.git.*|artisan|composer\\.(json|lock)|package(-lock)?\\.json|phpunit\\.xml.*|database/|storage/|vendor/|bootstrap/|config/|tests/|node_modules/) - [F,L,NC]' '$D/.htaccess' >/dev/null"
check "file mode of a new file is 644" "[ \"\$( (stat -c %a '$D/.htaccess' 2>/dev/null || stat -f %Lp '$D/.htaccess') )\" = 644 ]"
out="$(run write pres:press)"
check "second run: unchanged" "printf '%s' '$out' | grep -q '^pres:press: unchanged'"
same "second run: file identical" "$D/.htaccess" "$W/exp"
out="$(run undo pres:press)"
check "undo right after an 'unchanged' run has nothing to undo (the file stays)" "printf '%s' '$out' | grep -q 'nothing-to-undo' && [ -e '$D/.htaccess' ]"
rm -f "$D/.htaccess"; run write pres:press >/dev/null
out="$(run undo pres:press)"
check "undo after creating the file from nothing removes it again" "printf '%s' '$out' | grep -q 'undone' && [ ! -e '$D/.htaccess' ]"

# ---------- 2b. undo state is not stale ----------
reset; mk pres press; D="$(docroot pres)"
OTHER_RULES > "$D/.htaccess"; cp "$D/.htaccess" "$W/orig"
run write pres:press >/dev/null; run write pres:press >/dev/null   # 2nd run changes nothing and must drop the undo state
out="$(run undo pres:press)"
check "undo after an unchanged run does nothing (no stale snapshot)" "printf '%s' '$out' | grep -q 'nothing-to-undo'"
check "...and the guarded file is still guarded" "grep -q 'BEGIN dot-app-folder-guard press' '$D/.htaccess'"

# ---------- 3. file with other rules: block on top, rest byte-identical, one-time backup ----------
reset; mk pres press; D="$(docroot pres)"
OTHER_RULES > "$D/.htaccess"; chmod 640 "$D/.htaccess"; cp "$D/.htaccess" "$W/orig"
out="$(run write pres:press)"; rc=$?
check "other rules: 'written (prepended)', backup created" "[ $rc -eq 0 ] && printf '%s' '$out' | grep -q '^pres:press: written (prepended) backup=created\$'"
{ expected_block press; cat "$W/orig"; } > "$W/exp"
same "other rules: block on the very top, original bytes follow unchanged" "$D/.htaccess" "$W/exp"
same "other rules: backup equals the original" "$D/.htaccess.before-docroot-guard" "$W/orig"
check "other rules: first line is the BEGIN marker" "[ \"\$(head -1 '$D/.htaccess')\" = '# BEGIN dot-app-folder-guard press' ]"
check "other rules: file mode kept (640)" "[ \"\$( (stat -c %a '$D/.htaccess' 2>/dev/null || stat -f %Lp '$D/.htaccess') )\" = 640 ]"
cp "$D/.htaccess" "$W/after1"
out="$(run write pres:press)"
check "idempotent: second run says unchanged" "printf '%s' '$out' | grep -q '^pres:press: unchanged backup=kept\$'"
same "idempotent: file identical after the second run" "$D/.htaccess" "$W/after1"
same "idempotent: backup still the original" "$D/.htaccess.before-docroot-guard" "$W/orig"
check "no stray temp files in the docroot" "[ \"\$(ls -A '$D' | grep -c 'htaccess.new')\" = 0 ]"
# undo restores the original exactly
run write pres:press >/dev/null
out="$(run undo pres:press)"
check "undo with a prior snapshot: nothing to restore after an unchanged run" "printf '%s' '$out' | grep -q 'nothing-to-undo'"

# ---------- 4. block present but outdated: replaced in place ----------
reset; mk pres press; D="$(docroot pres)"
{ printf '%s\n' '# top rule'; printf '%s\n' '# BEGIN dot-app-folder-guard press' '<IfModule mod_rewrite.c>' 'RewriteEngine On' 'RewriteRule ^press/(\.env) - [F]' '</IfModule>' '# END dot-app-folder-guard press'; printf '%s\n' '# bottom rule' 'RewriteRule ^x$ y [L]'; } > "$D/.htaccess"
printf 'SENTINEL-BACKUP\n' > "$D/.htaccess.before-docroot-guard"
{ printf '%s\n' '# top rule'; expected_block press; printf '%s\n' '# bottom rule' 'RewriteRule ^x$ y [L]'; } > "$W/exp"
out="$(run write pres:press)"
check "outdated block: 'replaced'" "printf '%s' '$out' | grep -q '^pres:press: replaced backup=kept\$'"
same "outdated block: replaced in place; lines above and below untouched" "$D/.htaccess" "$W/exp"
check "existing backup is not overwritten" "[ \"\$(cat '$D/.htaccess.before-docroot-guard')\" = SENTINEL-BACKUP ]"
out="$(run undo pres:press)"
check "undo restores the outdated block exactly" "printf '%s' '$out' | grep -q 'undone' && grep -Fxq 'RewriteRule ^press/(\\.env) - [F]' '$D/.htaccess'"

# ---------- 5. markers respected ----------
reset; mk dot dot; mk dot dotfiles; D="$(docroot dot)"
{ printf '%s\n' '# keep me'; expected_block dotfiles; printf '%s\n' '#  BEGIN dot-app-folder-guard dot' '# END dot-app-folder-guard other' 'RewriteRule ^a$ b [L]'; } > "$D/.htaccess"
cp "$D/.htaccess" "$W/orig"
run write dot:dot >/dev/null
{ expected_block dot; cat "$W/orig"; } > "$W/exp"
same "key 'dot' leaves the 'dotfiles' block and look-alike comment lines alone (prepended above them)" "$D/.htaccess" "$W/exp"
run write dot:dotfiles >/dev/null
{ expected_block dot; cat "$W/orig"; } > "$W/exp"
same "'dotfiles' block already current: nothing else changes" "$D/.htaccess" "$W/exp"
# two keys in one docroot (different keys, same host)
reset; mk pres press; mk pres other; D="$(docroot pres)"
OTHER_RULES > "$D/.htaccess"; cp "$D/.htaccess" "$W/orig"
run write pres:press pres:other >/dev/null
{ expected_block other; expected_block press; cat "$W/orig"; } > "$W/exp"
same "two keys on one docroot: both blocks, each at the top, rest unchanged" "$D/.htaccess" "$W/exp"
# replace one without touching the other
sed 's#^RewriteRule ^other/#RewriteRule ^other/OLD/#' "$D/.htaccess" > "$W/mod"; cp "$W/mod" "$D/.htaccess"
sed 's#^RewriteRule ^press/#RewriteRule ^press/OLD/#' "$D/.htaccess" > "$W/mod"; cp "$W/mod" "$D/.htaccess"
run write pres:press >/dev/null
check "replacing the 'press' block leaves the (outdated) 'other' block exactly as it was" "grep -c 'OLD/' '$D/.htaccess' | grep -q '^1\$' && grep -Fq 'RewriteRule ^other/OLD/' '$D/.htaccess' && ! grep -Fq 'RewriteRule ^press/OLD/' '$D/.htaccess'"
# undo reverses in last-in-first-out order
reset; mk pres press; mk pres other; D="$(docroot pres)"
OTHER_RULES > "$D/.htaccess"; cp "$D/.htaccess" "$W/orig"
run write pres:press pres:other >/dev/null
run undo pres:press pres:other >/dev/null
same "undo of two pairs on one docroot restores the original bytes" "$D/.htaccess" "$W/orig"
# unbalanced marker: refused, file untouched
reset; mk pres press; D="$(docroot pres)"
{ printf '%s\n' 'RewriteRule ^a$ b [L]' '# BEGIN dot-app-folder-guard press' 'RewriteRule ^c$ d [L]'; } > "$D/.htaccess"; cp "$D/.htaccess" "$W/orig"
out="$(run write pres:press)"; rc=$?
check "BEGIN without END: refused, exit 3" "[ $rc -eq 3 ] && printf '%s' '$out' | grep -q 'refused'"
same "BEGIN without END: file untouched" "$D/.htaccess" "$W/orig"
check "BEGIN without END: no backup, no state" "[ ! -e '$D/.htaccess.before-docroot-guard' ] && [ ! -e '$HOME/.dot-docroot-guard/pres.press.undo' ]"
# stray END alone is just a comment outside any block
reset; mk pres press; D="$(docroot pres)"
printf '%s\n' '# END dot-app-folder-guard press' 'RewriteRule ^a$ b [L]' > "$D/.htaccess"; cp "$D/.htaccess" "$W/orig"
run write pres:press >/dev/null
{ expected_block press; cat "$W/orig"; } > "$W/exp"; same "a stray END line is left alone" "$D/.htaccess" "$W/exp"

# ---------- 6. Windows line endings ----------
reset; mk pres press; D="$(docroot pres)"
OTHER_RULES | sed 's/$/\r/' > "$D/.htaccess"; cp "$D/.htaccess" "$W/orig"
run write pres:press >/dev/null
{ expected_block press '\r'; cat "$W/orig"; } > "$W/exp"
same "CRLF file: block written with CRLF on top, original bytes unchanged" "$D/.htaccess" "$W/exp"
cp "$D/.htaccess" "$W/after1"
out="$(run write pres:press)"
check "CRLF file: second run is unchanged" "printf '%s' '$out' | grep -q 'unchanged'"
same "CRLF file: bytes identical after second run" "$D/.htaccess" "$W/after1"
# outdated CRLF block replaced, CRLF kept, outside bytes the same
{ printf '# BEGIN dot-app-folder-guard press\r\nRewriteRule ^press/(\\.env) - [F]\r\n# END dot-app-folder-guard press\r\n'; cat "$W/orig"; } > "$D/.htaccess"
out="$(run write pres:press)"
check "CRLF outdated block: 'replaced'" "printf '%s' '$out' | grep -q '^pres:press: replaced'"
same "CRLF outdated block: replaced with CRLF block, rest unchanged" "$D/.htaccess" "$W/exp"
# file without a final newline stays without one
reset; mk pres press; D="$(docroot pres)"
printf 'RewriteRule ^x$ y [L]' > "$D/.htaccess"; cp "$D/.htaccess" "$W/orig"
run write pres:press >/dev/null
{ expected_block press; cat "$W/orig"; } > "$W/exp"
same "no final newline in the original: kept missing" "$D/.htaccess" "$W/exp"
# empty existing file
reset; mk pres press; D="$(docroot pres)"; : > "$D/.htaccess"
run write pres:press >/dev/null; expected_block press > "$W/exp"
same "empty existing file gets just the block" "$D/.htaccess" "$W/exp"

# ---------- 7. plan (docroot-check) writes nothing ----------
reset; mk pres press; D="$(docroot pres)"
OTHER_RULES > "$D/.htaccess"; cp "$D/.htaccess" "$W/orig"
out="$(run plan pres:press)"; rc=$?
check "plan on a file without the block: would-write, exit 0" "[ $rc -eq 0 ] && printf '%s' '$out' | grep -q '^pres:press: would-write (prepended)\$'"
same "plan did not change the file" "$D/.htaccess" "$W/orig"
check "plan created no backup and no state" "[ ! -e '$D/.htaccess.before-docroot-guard' ] && [ ! -e '$HOME/.dot-docroot-guard' ]"
rm -f "$D/.htaccess"; out="$(run plan pres:press)"
check "plan with no .htaccess: would-write (new file); still no file" "printf '%s' '$out' | grep -q 'would-write (new file)' && [ ! -e '$D/.htaccess' ]"
run write pres:press >/dev/null; out="$(run plan pres:press)"
check "plan on a current block: unchanged" "printf '%s' '$out' | grep -q '^pres:press: unchanged'"
# plan on a missing app is informational (exit 0), write on it fails (exit 3)
reset; out="$(run plan pres:press)"; rc=$?
check "plan: missing docroot is reported, exit 0" "[ $rc -eq 0 ] && printf '%s' '$out' | grep -q 'skipped'"
out="$(run write pres:press)"; rc=$?
check "write: missing docroot is skipped and fails the run (exit 3)" "[ $rc -eq 3 ] && printf '%s' '$out' | grep -q 'skipped' && [ ! -e '$HOME/pres.infodot.co.za' ]"
mkdir -p "$(docroot pres)/press"
out="$(run write pres:press)"; rc=$?
check "write: docroot without a Laravel app (no artisan) is skipped, nothing written" "[ $rc -eq 3 ] && [ ! -e '$(docroot pres)/.htaccess' ]"

# existing rules that mention /<key>/ outside the block are counted (never printed)
reset; mk pres press; D="$(docroot pres)"
{ OTHER_RULES; printf '%s\n' 'RewriteRule ^storage/(.*)$ press/storage/app/public/$1 [L]'; } > "$D/.htaccess"
out="$(run plan pres:press)"
check "plan counts a rule outside the block that mentions press/ (and prints no rule text)" "printf '%s' '$out' | grep -q '^pres:press: would-write (prepended) refs-outside-block=1\$'"
out="$(run write pres:press)"; out2="$(run write pres:press)"
check "write reports it too, and the block's own rule line is not counted" "printf '%s' '$out' | grep -q 'refs-outside-block=1' && printf '%s' '$out2' | grep -q '^pres:press: unchanged backup=kept refs-outside-block=1\$'"
reset; mk pres press; D="$(docroot pres)"; OTHER_RULES > "$D/.htaccess"
out="$(run plan pres:press)"
check "no reference outside the block: no refs field" "! printf '%s' '$out' | grep -q refs-outside"

# ---------- 8. symlink refused ----------
reset; mk pres press; D="$(docroot pres)"
printf 'x\n' > "$W/elsewhere"; ln -s "$W/elsewhere" "$D/.htaccess"
out="$(run write pres:press)"; rc=$?
check "a symlinked .htaccess is refused and its target untouched" "[ $rc -eq 3 ] && [ \"\$(cat '$W/elsewhere')\" = x ]"

# ---------- 9. the rule is narrow: which URLs does the pattern match? (grep -E stands in for mod_rewrite's PCRE; the
#              pattern uses only constructs both share. [NC] = case-insensitive.) ----------
reset; mk pres press; run write pres:press >/dev/null
PAT="$(grep '^RewriteRule' "$(docroot pres)/.htaccess" | sed -E 's/^RewriteRule +([^ ]+) .*/\1/')"
matches() { printf '%s\n' "$1" | grep -Eiq -- "$PAT"; }
for u in press/.env press/.env.bak-secrets press/.env.production press/.git/config press/.gitignore press/artisan press/composer.json press/composer.lock press/package.json press/package-lock.json press/phpunit.xml press/phpunit.xml.dist press/database/database.sqlite press/storage/logs/laravel.log press/vendor/autoload.php press/bootstrap/cache/config.php press/config/app.php press/tests/Feature/X.php press/node_modules/x/index.js press/VENDOR/autoload.php press/.ENV; do
  check "blocked: /$u" "matches '$u'"
done
for u in '' login up press press/ press/public/index.php press/public/storage/a.png press/public/build/assets/app.js press/build/app.js press/login press/up press/app/Models/User.php press/resources/views/a.blade.php press/routes/web.php press/envelope press/readme.md press/public/.well-known/security.txt build/assets/app.css storage/x vendor/x database/x .env artisan composer.json other/vendor/autoload.php pressing/vendor/x xpress/vendor/x public/storage/a.png favicon.ico robots.txt; do
  check "not blocked: /$u" "! matches '$u'"
done

# ---------- 10. probe-exposure.sh: unchanged without env, timeline with retries ----------
SHIM="$W/shim"; mkdir -p "$SHIM" "$W/curlstate"
cat > "$SHIM/curl" <<'SH'
#!/bin/sh
# fake curl: last argument is the URL; prints a status code. TABLE lines: "<url> <code1,code2,...>" (nth call -> nth code, last repeats).
for a; do url="$a"; done
key=$(printf '%s' "$url" | tr -c 'a-zA-Z0-9' '_')
n=$(cat "$CURL_STATE/$key" 2>/dev/null || echo 0); n=$((n + 1)); echo "$n" > "$CURL_STATE/$key"
row=$(grep -F "$url " "$CURL_TABLE" 2>/dev/null | head -1)
if [ -n "$row" ]; then
  list=${row#* }; i=1; code=
  for c in $(printf '%s' "$list" | tr ',' ' '); do code=$c; [ "$i" -ge "$n" ] && break; i=$((i + 1)); done
  printf '%s' "$code"; exit 0
fi
case "$url" in */|*/up|*/login) printf 200 ;; *) printf 403 ;; esac
SH
chmod +x "$SHIM/curl"
export PATH="$SHIM:$PATH" CURL_STATE="$W/curlstate" CURL_TABLE="$W/table"
newstate() { rm -rf "$W/curlstate"; mkdir -p "$W/curlstate"; : > "$W/table"; }
P="$ROOT/tools/probe-exposure.sh"

newstate
out="$(bash "$P" pres:press)"; rc=$?
check "probe, no env, all blocked: exit 0 and the old single-pass output" "[ $rc -eq 0 ] && [ \"\$(printf '%s\n' '$out' | head -1)\" = 'pres: site / 200 /up 200 | .env=403 .env.bak-secrets=403 artisan=403 composer.json=403 composer.lock=403 package.json=403 database/database.sqlite=403 storage/logs/laravel.log=403 vendor/autoload.php=403 phpunit.xml=403' ] && printf '%s' '$out' | tail -1 | grep -qx 'No app folder is readable.'"
check "probe, no env: no 'attempt' prefix" "! printf '%s' '$out' | grep -q attempt"

newstate; echo "https://pres.infodot.co.za/press/.env 200" > "$W/table"
out="$(bash "$P" pres:press)"; rc=$?
check "probe, no env, exposed: exit 1, EXPOSED tag, old final line" "[ $rc -eq 1 ] && printf '%s' '$out' | grep -q '.env=200(EXPOSED)' && printf '%s' '$out' | tail -1 | grep -qx 'EXPOSED: at least one app folder answers over HTTP.'"

newstate; echo "https://infodot.co.za/main/storage/logs/laravel.log 200" > "$W/table"
out="$(bash "$P" apex:main)"; rc=$?
check "probe, apex:main: the main domain's own app folder is probed at https://infodot.co.za/main/..., exposed -> exit 1" "[ $rc -eq 1 ] && printf '%s\n' '$out' | grep -q 'storage/logs/laravel.log=200(EXPOSED)'"
newstate
out="$(bash "$P" apex:main)"; rc=$?
check "probe, apex:main, all blocked: exit 0" "[ $rc -eq 0 ] && printf '%s\n' '$out' | grep -q '^apex: site /'"
newstate; echo "https://pres.infodot.co.za/press/.env 200,403,403" > "$W/table"
out="$(PROBE_RETRIES=3 PROBE_WAIT=0 bash "$P" pres:press)"; rc=$?
check "probe, transient window: exit still 1 (any 200 ever)" "[ $rc -eq 1 ]"
check "probe, transient: all 3 attempts printed with their number" "[ \$(printf '%s\n' '$out' | grep -c '^attempt [123]/3 t+[0-9]*s: pres:') = 3 ]"
check "probe, transient: the 200 shows on attempt 1 only" "printf '%s\n' '$out' | grep '^attempt 1/3' | grep -q '.env=200(EXPOSED)' && ! printf '%s\n' '$out' | grep '^attempt [23]/3' | grep -q EXPOSED"
check "probe, transient: classified transient with the timeline" "printf '%s\n' '$out' | grep -qx 'EXPOSED transient: pres:press .env attempts: 1=200 2=403 3=403'"
check "probe, transient: final line counts 0 persistent / 1 transient" "printf '%s' '$out' | tail -1 | grep -qx 'EXPOSED: at least one app folder answered 200 at some attempt (0 persistent, 1 transient).'"

newstate; echo "https://pres.infodot.co.za/press/artisan 403,200,200" > "$W/table"
out="$(PROBE_RETRIES=3 PROBE_WAIT=0 bash "$P" pres:press)"; rc=$?
check "probe, persistent (200 on the last attempt): exit 1, classified persistent" "[ $rc -eq 1 ] && printf '%s\n' '$out' | grep -qx 'EXPOSED persistent: pres:press artisan attempts: 1=403 2=200 3=200'"

newstate
out="$(PROBE_RETRIES=2 PROBE_WAIT=0 bash "$P" pres:press doc:doc)"; rc=$?
check "probe, retries and clean: exit 0, 4 attempt lines, no EXPOSED word besides none" "[ $rc -eq 0 ] && [ \$(printf '%s\n' '$out' | grep -c '^attempt') = 4 ] && ! printf '%s' '$out' | grep -q EXPOSED"
PROBE_RETRIES=0 bash "$P" pres:press >/dev/null 2>&1; check "probe: PROBE_RETRIES=0 rejected" "[ \$? -eq 2 ]"
PROBE_RETRIES=abc bash "$P" pres:press >/dev/null 2>&1; check "probe: PROBE_RETRIES=abc rejected" "[ \$? -eq 2 ]"
PROBE_WAIT=-1 bash "$P" pres:press >/dev/null 2>&1; check "probe: PROBE_WAIT=-1 rejected" "[ \$? -eq 2 ]"
START=$(date +%s); PROBE_RETRIES=2 PROBE_WAIT=2 bash "$P" pres:press >/dev/null; EL=$(( $(date +%s) - START ))
check "probe: PROBE_WAIT=2 waits between attempts (took $EL s)" "[ $EL -ge 2 ]"

# ---------- 11. site-status.sh and the summary ----------
newstate; echo "https://pres.infodot.co.za/login 302" > "$W/table"
out="$(bash "$ROOT/tools/site-status.sh" pres doc)"
check "site-status: one stable line per host" "[ \"\$(printf '%s\n' '$out' | head -1)\" = 'pres: / 200 /up 200 /login 302' ] && [ \"\$(printf '%s\n' '$out' | tail -1)\" = 'doc: / 200 /up 200 /login 200' ]"
bash "$ROOT/tools/site-status.sh" 'bad host' >/dev/null 2>&1; check "site-status: bad host label rejected" "[ \$? -eq 2 ]"
printf '%s\n' 'pres:press: written (prepended) backup=created' 'doc:doc: unchanged backup=kept' > "$W/act"
printf '%s\n' 'pres: / 200 /up 200 /login 302' 'doc: / 200 /up 200 /login 200' > "$W/bef"
printf '%s\n' 'pres: / 200 /up 200 /login 302' 'doc: / 200 /up 500 /login 200' > "$W/aft"
printf '%s\n' 'EXPOSED transient: pres:press .env attempts: 1=200 2=403 3=403' 'EXPOSED: at least one app folder answered 200 at some attempt (0 persistent, 1 transient).' > "$W/prb"
sum="$(bash "$ROOT/tools/docroot-guard-summary.sh" docroot "$W/act" "$W/bef" "$W/aft" "$W/prb")"
check "summary: a table row per pair with the host result" "printf '%s\n' '$sum' | grep -q '^| pres:press | written (prepended) backup=created | 200 (same) | 200 (same) | 302 (same) | transient only: .env |'"
check "summary: a changed status is flagged" "printf '%s\n' '$sum' | grep -q '^| doc:doc | unchanged backup=kept | 200 (same) | 200 to 500 (CHANGED) |'"
sum="$(bash "$ROOT/tools/docroot-guard-summary.sh" docroot-check "$W/act" /nonexistent /nonexistent /nonexistent)"
check "summary works with missing files" "printf '%s\n' '$sum' | grep -q 'not probed'"

# ---------- a moved app (document root already <app>/public) is left alone ----------
reset; mk pres press; D="$(docroot pres)"
: > "$D/press/.docroot-is-public"
for act in plan write; do
  out="$(run $act pres:press)"; rc=$?
  check "moved app (marker): $act skips it, exit 0, writes nothing" "[ $rc -eq 0 ] && printf '%s' '$out' | grep -q '^pres:press: skipped (document root is already press/public' && [ ! -e '$D/.htaccess' ] && [ ! -e '$HOME/.dot-docroot-guard' ]"
done
reset; mk pres press; D="$(docroot pres)"; mkdir -p "$W/bin"
REAL="$(cd "$D/press" && pwd -P)"
printf '#!/bin/sh\nprintf %%s\\n '"'"'{"result":{"data":{"documentroot":"%s/public"}}}'"'"'\n' "$REAL" > "$W/bin/uapi"; chmod +x "$W/bin/uapi"
out="$(PATH="$W/bin:$PATH" run write pres:press)"; rc=$?
check "moved app (host says <app>/public): write skips it, exit 0, nothing written" "[ $rc -eq 0 ] && printf '%s' '$out' | grep -q 'skipped (document root is already' && [ ! -e '$D/.htaccess' ]"
printf '#!/bin/sh\nprintf %%s\\n '"'"'{"result":{"data":{"documentroot":"/somewhere/else"}}}'"'"'\n' > "$W/bin/uapi"
out="$(PATH="$W/bin:$PATH" run write pres:press)"; rc=$?
check "unmoved app (host says another root): the guard block is still written" "[ $rc -eq 0 ] && printf '%s' '$out' | grep -q '^pres:press: written (new file)' && [ -e '$D/.htaccess' ]"

echo; echo "$N checks, $( [ "$FAIL" = 0 ] && echo 'all passed' || echo 'SOME FAILED' )"
exit "$FAIL"
