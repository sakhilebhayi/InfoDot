#!/usr/bin/env bash
# Fake-host test of the App Folder Guard mode docroot-inventory: tools/docroot-inventory-host.sh (the very script the
# workflow pipes to the host) and tools/docroot-inventory-summary.sh. No network, no SSH: HOME is a temp dir and `crontab`
# is a stub that records how it is called. Runs in CI (Linux) and locally (macOS bash 3.2 ok).
# The core requirement is READ-ONLY: section 1 greps the script for any write operation, section 12 compares a checksum of
# the whole temp HOME (names, modes, mtimes, contents, link targets) before and after running every layout, and runs the
# script once more on a HOME made read-only. It cannot show how the real host behaves (LiteSpeed, cPanel, real crontab).
# shellcheck disable=SC2034
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
W="$(mktemp -d)"
trap 'chmod -R u+w "$W" 2>/dev/null; rm -rf "$W"' EXIT
export HOME="$W/home"; mkdir -p "$HOME" "$W/shim" "$W/state" "$W/tmp"
export TMPDIR="$W/tmp"
HOST="$ROOT/tools/docroot-inventory-host.sh"
SUM="$ROOT/tools/docroot-inventory-summary.sh"
FAIL=0; N=0
ok() { N=$((N + 1)); echo "ok   - $1"; }
bad() { N=$((N + 1)); echo "FAIL - $1"; FAIL=1; }
check() { if eval "$2"; then ok "$1"; else bad "$1"; fi; }

# ---- the crontab stub: only `-l` is accepted; every call is logged OUTSIDE the temp HOME ----
cat > "$W/shim/crontab" <<'SH'
#!/bin/sh
echo "$*" >> "$CRON_CALLS"
if [ "$1" != "-l" ]; then echo "stub crontab: a write was attempted" >&2; echo "$*" >> "$CRON_CALLS.write"; exit 9; fi
n=$(cat "$CRON_N" 2>/dev/null || echo 0); n=$((n + 1)); echo "$n" > "$CRON_N"
if [ "$n" -le "${CRON_EMPTY_FIRST:-0}" ]; then exit 0; fi
[ -f "$CRON_FILE" ] && cat "$CRON_FILE"
exit 0
SH
chmod +x "$W/shim/crontab"
export PATH="$W/shim:$PATH" CRON_CALLS="$W/state/calls" CRON_N="$W/state/n" CRON_FILE="$W/state/crontab.txt" INVENTORY_CRON_RETRY_WAIT=0
cron_reset() { rm -f "$CRON_CALLS" "$CRON_CALLS.write" "$CRON_N" "$CRON_FILE"; unset CRON_EMPTY_FIRST; }

SECRET1="PLANTED-MARKER-ENV"; SECRET2="PLANTED-MARKER-HTACCESS"; SECRET3="PLANTED-MARKER-CRON"; SECRET4="PLANTED-MARKER-SHIM"; SECRET5="PLANTED-MARKER-INI"
LARAVEL_HT='<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews -Indexes
    </IfModule>

    RewriteEngine On

    # Handle Authorization Header
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]

    # Redirect Trailing Slashes If Not A Folder...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_URI} (.+)/$
    RewriteRule ^ %1 [L,R=301]

    # Send Requests To Front Controller...
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^ index.php [L]
</IfModule>'

docroot() { echo "$HOME/$1.infodot.co.za"; }
reset() { case "$HOME" in "$W"/*) ;; *) echo "refusing to reset a HOME outside the temp dir" >&2; exit 1 ;; esac; chmod -R u+w "$HOME" 2>/dev/null; rm -rf "$HOME"; mkdir -p "$HOME"; cron_reset; }
mk_std() { # $1 = host, $2 = key: a standard app the way prepare-host.sh leaves it, with planted secrets
  local d k; d="$(docroot "$1")"; k="$2"
  mkdir -p "$d/$k/public/build/assets" "$d/$k/storage/app/public" "$d/$k/storage/logs" "$d/$k/database"
  : > "$d/$k/artisan"
  printf 'PLANTED_ONE=%s\nPLANTED_TWO=%s\n' "$SECRET1" "$SECRET1" > "$d/$k/.env"
  printf '%s\n' "$SECRET1" > "$d/$k/storage/logs/laravel.log"; printf 'SQLite %s' "$SECRET1" > "$d/$k/database/database.sqlite"
  printf '<?php // %s\n' "$SECRET1" > "$d/$k/public/index.php"
  printf '%s\n' "$LARAVEL_HT" > "$d/$k/public/.htaccess"; cp "$d/$k/public/.htaccess" "$d/.htaccess"
  printf 'User-agent: *\n' > "$d/$k/public/robots.txt"; printf 'ico' > "$d/$k/public/favicon.ico"
  printf '<?php\nrequire __DIR__.%s;\n' "'/$k/public/index.php'" > "$d/index.php"
  ln -s "$k/public/build" "$d/build"; ln -s "$k/public/robots.txt" "$d/robots.txt"; ln -s "$k/public/favicon.ico" "$d/favicon.ico"
}
OUT=""; RC=0
run() { OUT="$(bash "$HOST" "$@" 2>&1)"; RC=$?; }
has() { printf '%s\n' "$OUT" | grep -Fxq -- "$1"; }
hasre() { printf '%s\n' "$OUT" | grep -Eq -- "$1"; }
lacks() { ! printf '%s\n' "$OUT" | grep -Fq -- "$1"; }
verdict() { printf '%s\n' "$OUT" | grep -F -- "$1: verdict: " | head -1 | sed "s/^$1: verdict: //"; }
v_is() { [ "$(verdict "$1")" = "$2" ]; }
v_has() { case "$(verdict "$1")" in *"$2"*) return 0 ;; esac; return 1; }

# ================= 1. READ-ONLY, static: the script contains no write operation =================
CODE="$(grep -v '^[[:space:]]*#' "$HOST")"
for w in rm mv ln chmod chown chgrp cp touch mkdir rmdir tee mktemp dd truncate install rsync scp ssh curl wget eval source exec sudo kill unlink shred mkfifo mknod flock; do
  check "static: no '$w' command in the host script" "! printf '%s\n' \"\$CODE\" | grep -Eq '(^|[^A-Za-z0-9_./-])$w([^A-Za-z0-9_-]|\$)'"
done
check "static: no find -exec / -delete / -fprint / -ok" "! printf '%s\n' \"\$CODE\" | grep -Eq -- '-(exec|execdir|delete|fprint|fprintf|ok|okdir)([^a-z]|\$)'"
check "static: no sed -i" "! printf '%s\n' \"\$CODE\" | grep -Eq 'sed +(-[a-zA-Z]*i|--in-place)'"
check "static: no awk system() / getline / pipe-to-command" "! printf '%s\n' \"\$CODE\" | grep -Eq 'system *\\(|getline'"
check "static: no here-document or here-string (older bash writes a temp file for those)" "! printf '%s\n' \"\$CODE\" | grep -q '<<'"
REDIR="$(printf '%s\n' "$CODE" | sed -E 's#2>/dev/null##g; s#>/dev/null##g; s#2>&1##g; s#>&2##g')"
check "static: after removing >/dev/null, 2>/dev/null, 2>&1, >&2 the code has no output redirection at all" "! printf '%s\n' \"\$REDIR\" | grep -q '>'"
check "static: no '>>' append" "! printf '%s\n' \"\$CODE\" | grep -q '>>'"
check "static: crontab appears only as 'command -v crontab' and 'crontab -l'" "[ \"\$(printf '%s\n' \"\$CODE\" | grep -c crontab)\" = 2 ] && printf '%s\n' \"\$CODE\" | grep -q 'command -v crontab' && printf '%s\n' \"\$CODE\" | grep -q 'TO 10 crontab -l 2>/dev/null'"
check "static: crontab -l is read in one place only (one call site)" "[ \"\$(printf '%s\n' \"\$CODE\" | grep -c 'crontab -l')\" = 1 ]"
check "static: no variable assignment from an env file, no 'cat' of anything" "! printf '%s\n' \"\$CODE\" | grep -Eq '(^|[^A-Za-z0-9_-])(cat|less|more|source)([^A-Za-z0-9_-]|\$)' && ! printf '%s\n' \"\$CODE\" | grep -q '\\.env'"
bash -n "$HOST" && ok "static: bash -n" || bad "static: bash -n"

# ================= 2. input validation =================
reset; mk_std pres press
for badpair in 'pres:Press' 'pres:pre ss' 'pres:../x' 'pres:p;ls' 'Pres:press' 'pres' ':press' 'pres:' 'pres:press:x' 'pres:pre$ss' 'pres:a|b' 'pres:a.b' 'pres:a/b'; do
  run "$badpair"; check "bad pair [$badpair] is rejected (exit 2, no report)" "[ $RC -eq 2 ] && ! printf '%s' \"\$OUT\" | grep -q '^inventory:'"
done
run pres:press 'pres:bad key'; check "one bad pair among good ones rejects the whole call before any output line" "[ $RC -eq 2 ] && ! printf '%s' \"\$OUT\" | grep -q 'verdict'"
bash "$HOST" >/dev/null 2>&1; check "no pair is rejected" "[ \$? -eq 2 ]"
INVENTORY_CRON_RETRY_WAIT=x bash "$HOST" pres:press >/dev/null 2>&1; check "a non-numeric retry wait is rejected" "[ \$? -eq 2 ]"

# ================= 3. a standard app is READY and the facts are reported =================
reset; mk_std pres press; D="$(docroot pres)"
run pres:press
P=pres:press
check "standard: exit 0" "[ $RC -eq 0 ]"
has "inventory: read-only, 1 pair(s)" && ok "standard: header line" || bad "standard: header line"
for l in "docroot: ~/pres.infodot.co.za" "app: ~/pres.infodot.co.za/press" "layout: standard" "app_parent_is_docroot: yes" \
         "entry: symlink build to=press/public/build exists=yes in_public=yes" "entry: symlink robots.txt to=press/public/robots.txt exists=yes in_public=yes" \
         "entry: dir press" "entry: file index.php size=49" "public_dir: dir" "public_index_php: yes" "public_htaccess: yes" "public_build: dir" \
         "public_images: missing" "public_storage: missing" "public_favicons: favicon.ico" "asset: build docroot=symlink public=dir" "asset: images docroot=missing public=missing" \
         "docroot_index_php: file size=49" "shim: standard" "htaccess_diff_signal: none" "htaccess_docroot_has_auth_header_passthrough: yes" \
         "htaccess_docroot_has_https_redirect: no" "htaccess_docroot_has_headers: no" "htaccess_docroot_has_php_handler_lines: no" "php_ini: docroot=none public=none" \
         "uploads_via_symlink: no" "public_storage_files: n/a" "verdict: READY"; do
  has "$P: $l" && ok "standard: '$l'" || { bad "standard: '$l'"; }
done
has "summary: READY=1 NEEDS-ATTENTION=0 UNKNOWN-LAYOUT=0 pairs=1" && ok "standard: summary line" || bad "standard: summary line"
check "standard: no absolute HOME path in the output (paths are shown with ~)" "lacks '$HOME'"
check "standard: public_size_kb is a number" "hasre '^$P: public_size_kb: [0-9]+\$'"
check "standard: the docroot .htaccess directive list is sorted names with counts" "has '$P: htaccess_docroot_directives: <IfModule>=2 Options=1 RewriteCond=5 RewriteEngine=1 RewriteRule=3'"
check "standard: file entries never appear with a content" "lacks 'User-agent'"

# docroot index.php shim variants -> standard; others -> custom
for variant in 'oneline' 'crlf' 'nofinalnl' 'spaces'; do
  reset; mk_std pres press; D="$(docroot pres)"
  case "$variant" in
    oneline) printf "<?php require __DIR__.'/press/public/index.php';\n" > "$D/index.php" ;;
    crlf) printf "<?php\r\nrequire __DIR__.'/press/public/index.php';\r\n" > "$D/index.php" ;;
    nofinalnl) printf "<?php\nrequire __DIR__.'/press/public/index.php';" > "$D/index.php" ;;
    spaces) printf "<?php\n\nrequire   __DIR__.'/press/public/index.php';\n\n" > "$D/index.php" ;;
  esac
  run pres:press; check "shim variant '$variant' is standard (and READY)" "has '$P: shim: standard' && v_is $P READY"
done
reset; mk_std pres press; D="$(docroot pres)"
printf '<?php\n$k = "%s";\nrequire __DIR__.%s;\n' "$SECRET4" "'/press/public/index.php'" > "$D/index.php"
run pres:press
check "custom shim (extra code): shim=custom, NEEDS-ATTENTION(custom-shim)" "has '$P: shim: custom' && v_is $P 'NEEDS-ATTENTION(custom-shim)'"
check "custom shim: the content (a planted secret) is not printed" "lacks '$SECRET4'"
check "custom shim: reason line present" "has '$P: reason: custom-shim'"
reset; mk_std pres press; D="$(docroot pres)"
printf "<?php\nrequire __DIR__.'/other/public/index.php';\n" > "$D/index.php"; run pres:press
check "shim pointing at another folder is custom" "has '$P: shim: custom'"
reset; mk_std pres press; D="$(docroot pres)"; head -c 9000 /dev/zero | tr '\0' 'a' > "$D/index.php"; run pres:press
check "a 9000-byte index.php is custom without being read in full" "has '$P: shim: custom' && has '$P: docroot_index_php: file size=9000'"
reset; mk_std pres press; D="$(docroot pres)"; rm "$D/index.php"; run pres:press
check "no docroot index.php: shim=missing, reason" "has '$P: shim: missing' && v_has $P docroot-index.php-missing"

# ================= 4. public/.htaccess and friends missing =================
reset; mk_std pres press; D="$(docroot pres)"; rm "$D/press/public/.htaccess"; run pres:press
check "public/.htaccess missing: NEEDS-ATTENTION with the reason" "v_has $P 'public/.htaccess-missing' && has '$P: public_htaccess: no' && has '$P: htaccess_public: missing'"
check "public/.htaccess missing: the docroot's directive names are then all docroot-only" "v_has $P 'docroot-htaccess-directives-not-in-public(<IfModule>,Options,RewriteCond,RewriteEngine,RewriteRule)'"
reset; mk_std pres press; D="$(docroot pres)"; rm "$D/press/public/index.php"; run pres:press
check "public/index.php missing: reason" "v_has $P 'public/index.php-missing' && has '$P: public_index_php: no'"
reset; mk_std pres press; D="$(docroot pres)"; rm -rf "$D/press/public"; run pres:press
check "public/ missing: reason public-dir-missing, no crash, symlinks into it reported broken" "[ $RC -eq 0 ] && v_has $P public-dir-missing && v_has $P 'docroot-symlink-broken(build,favicon.ico,robots.txt)' && has '$P: public_dir: missing' && has '$P: public_size_kb: n/a'"

# ================= 5. extra directives in the docroot .htaccess =================
reset; mk_std pres press; D="$(docroot pres)"
{ cat "$D/.htaccess"; printf '%s\n' '<IfModule mod_headers.c>' '  Header always set X-Frame-Options "SAMEORIGIN"' '</IfModule>' 'AddHandler application/x-httpd-ea-php83 .php' 'php_value upload_max_filesize 64M'; } > "$D/h"; mv "$D/h" "$D/.htaccess"
run pres:press
check "extra Header/php lines: flags yes" "has '$P: htaccess_docroot_has_headers: yes' && has '$P: htaccess_docroot_has_php_handler_lines: yes' && has '$P: htaccess_public_has_headers: no' && has '$P: htaccess_public_has_php_handler_lines: no'"
check "extra Header/php lines: diff signal lists the names only" "has '$P: htaccess_diff_signal: docroot-only AddHandler,Header,php_value'"
check "extra Header/php lines: NEEDS-ATTENTION with the names" "v_is $P 'NEEDS-ATTENTION(docroot-htaccess-directives-not-in-public(AddHandler,Header,php_value))'"
check "extra Header/php lines: the header value is not printed" "lacks SAMEORIGIN && lacks 64M"

reset; mk_std pres press; D="$(docroot pres)"
printf '%s\n' "# cPanel-generated handler $SECRET2" '<IfModule mime_module>' 'AddHandler application/x-httpd-ea-php83 .php .php8 .phtml' '</IfModule>' >> "$D/.htaccess"; run pres:press
check "cPanel PHP block is flagged as such" "has '$P: htaccess_docroot_cpanel_php_block: yes' && has '$P: htaccess_docroot_has_php_handler_lines: yes' && v_has $P 'AddHandler'"

# the https redirect, the auth passthrough, directive-list details, no leaks
reset; mk_std pres press; D="$(docroot pres)"
cat > "$D/.htaccess" <<EOF
# top comment $SECRET2
<IfModule mod_headers.c>
  Header always set X-Frame-Options "SAMEORIGIN"
  Header set Strict-Transport-Security "max-age=63072000"
</IfModule>
RewriteEngine On
RewriteCond %{HTTPS} off
RewriteRule ^(.*)\$ https://%{HTTP_HOST}/\$1 [R=301,L]
RewriteCond %{HTTP:Authorization} .
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
php_value upload_max_filesize 64M
ErrorDocument 404 /404.html
SetEnv PLANTED_VAR $SECRET2
<FilesMatch "^\.">
  Require all denied
</FilesMatch>
Options -Indexes
bogus_directive $SECRET2
$SECRET2-bare-line
# BEGIN dot-app-folder-guard press
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteRule ^press/(\.env.*) - [F,L,NC]
</IfModule>
# END dot-app-folder-guard press
EOF
run pres:press
check "directive list: sorted, deduplicated, with counts; guard block excluded; SetEnv listed by name only" "has '$P: htaccess_docroot_directives: <FilesMatch>=1 <IfModule>=1 ErrorDocument=1 Header=2 Options=1 Require=1 RewriteCond=2 RewriteEngine=1 RewriteRule=2 SetEnv=1 php_value=1'"
check "directive list: unknown lines are counted, not named" "has '$P: htaccess_docroot_unrecognised_lines: 2'"
check "flags: https redirect, headers, php, auth passthrough, guard block" "has '$P: htaccess_docroot_has_https_redirect: yes' && has '$P: htaccess_docroot_has_headers: yes' && has '$P: htaccess_docroot_has_php_handler_lines: yes' && has '$P: htaccess_docroot_has_auth_header_passthrough: yes' && has '$P: htaccess_docroot_dot_guard_block: yes'"
check "https redirect only in the docroot: reason" "v_has $P docroot-https-redirect-not-in-public"
check "no .htaccess comment, value or bare line leaks into the output" "lacks '$SECRET2' && lacks PLANTED_VAR && lacks bogus_directive && lacks 'top comment'"
# auth passthrough in the docroot but not in public
reset; mk_std pres press; D="$(docroot pres)"
printf '%s\n' '<IfModule mod_rewrite.c>' 'RewriteEngine On' 'RewriteRule ^ index.php [L]' '</IfModule>' > "$D/press/public/.htaccess"; run pres:press
check "auth passthrough only in the docroot: reason" "v_has $P docroot-auth-header-passthrough-not-in-public"
# CRLF .htaccess is read the same way
reset; mk_std pres press; D="$(docroot pres)"; sed 's/$/\r/' "$D/.htaccess" > "$D/h"; mv "$D/h" "$D/.htaccess"; run pres:press
check "CRLF docroot .htaccess: same directive names, still READY" "has '$P: htaccess_docroot_directives: <IfModule>=2 Options=1 RewriteCond=5 RewriteEngine=1 RewriteRule=3' && v_is $P READY"
# no docroot .htaccess at all
reset; mk_std pres press; D="$(docroot pres)"; rm "$D/.htaccess"; run pres:press
check "no docroot .htaccess: reported missing, no diff, READY" "has '$P: htaccess_docroot: missing' && has '$P: htaccess_diff_signal: n/a' && v_is $P READY"

# ================= 6. .user.ini / php.ini =================
reset; mk_std pres press; D="$(docroot pres)"; printf 'memory_limit=%s\n' "$SECRET5" > "$D/.user.ini"; : > "$D/php.ini"; run pres:press
check ".user.ini + php.ini in the docroot only: names, reason, no content" "has '$P: php_ini: docroot=.user.ini,php.ini public=none' && v_has $P 'docroot-php-ini-not-in-public(.user.ini,php.ini)' && lacks '$SECRET5'"
cp "$D/.user.ini" "$D/press/public/.user.ini"; : > "$D/press/public/php.ini"; run pres:press
check ".user.ini + php.ini in both: READY" "has '$P: php_ini: docroot=.user.ini,php.ini public=.user.ini,php.ini' && v_is $P READY"

# ================= 7. symlinks, uploads, other docroot entries =================
reset; mk_std pres press; D="$(docroot pres)"; ln -s press/storage/app/public "$D/storage"; run pres:press
check "storage -> app/storage/app/public with no public/storage: both reasons, uploads flagged" "v_has $P 'public/storage-missing-while-uploads-symlink-exists' && v_has $P 'docroot-symlink-outside-public(storage)' && has '$P: uploads_via_symlink: yes (docroot/storage)' && has '$P: entry: symlink storage to=press/storage/app/public exists=yes in_public=no'"
reset; mk_std pres press; D="$(docroot pres)"; ln -s ../storage/app/public "$D/press/public/storage"; ln -s press/public/storage "$D/storage"
mkdir -p "$D/press/storage/app/public/a/b"; : > "$D/press/storage/app/public/a/one.png"; : > "$D/press/storage/app/public/a/b/two.png"; : > "$D/press/storage/app/public/three.pdf"
run pres:press
check "storage via a relative public/storage link: READY, uploads flagged (both links), 3 files counted, no names" "v_is $P READY && has '$P: uploads_via_symlink: yes (docroot/storage,public/storage)' && has '$P: public_storage: symlink to=../storage/app/public exists=yes' && has '$P: public_storage_files: 3' && lacks three.pdf"
reset; mk_std pres press; D="$(docroot pres)"; mkdir -p "$D/press/storage/app/public/a/b"; : > "$D/press/storage/app/public/a/one.png"; : > "$D/press/storage/app/public/a/b/two.png"; : > "$D/press/storage/app/public/three.pdf"
ln -s "$D/press/storage/app/public" "$D/press/public/storage"; ln -s press/public/storage "$D/storage"; run pres:press
check "storage via an absolute public/storage link: READY, three files counted, no names" "v_is $P READY && has '$P: public_storage: symlink to=storage/app/public exists=yes' && has '$P: public_storage_files: 3' && has '$P: uploads_via_symlink: yes (docroot/storage,public/storage)' && lacks three.pdf"
reset; mk_std pres press; D="$(docroot pres)"; ln -s gone "$D/press/public/storage"; run pres:press
check "public/storage that points nowhere: reason" "v_has $P public/storage-symlink-broken && has '$P: public_storage: symlink to=gone exists=no'"
reset; mk_std pres press; D="$(docroot pres)"; ln -s press/storage/uploads "$D/uploads"; run pres:press
check "docroot 'uploads' link to nowhere: broken-symlink reason, flagged, public/storage reason" "v_has $P 'docroot-symlink-broken(uploads)' && has '$P: uploads_via_symlink: yes (docroot/uploads)' && v_has $P public/storage-missing-while-uploads-symlink-exists"
reset; mk_std pres press; D="$(docroot pres)"; mkdir -p "$D/press/storage/img"; ln -s press/storage/img "$D/images"; run pres:press
check "docroot symlink to something other than public/*: reason names it" "v_has $P 'docroot-symlink-outside-public(images)' && has '$P: asset: images docroot=symlink public=missing'"
reset; mk_std pres press; D="$(docroot pres)"; ln -s "../../x" "$D/up"; run pres:press
check "a '..' symlink is outside public (and broken here)" "v_has $P 'docroot-symlink-broken(up)'"
reset; mk_std pres press; D="$(docroot pres)"; mkdir -p "$D/press/public/images"; ln -s press/public/images "$D/images"; ln -s press/public/images "$D/img"; run pres:press
check "docroot link to public/images under another name: docroot-only (public has no 'img')" "v_is $P 'NEEDS-ATTENTION(docroot-only-entries(img))'"
reset; mk_std pres press; D="$(docroot pres)"; printf 'x' > "$D/old-page.html"; mkdir "$D/legacy"; printf 'hello' > "$D/ads.txt"; : > "$D/press/public/ads.txt"; run pres:press
check "real files and dirs in the docroot that public lacks are listed; ones public has are not" "v_is $P 'NEEDS-ATTENTION(docroot-only-entries(legacy,old-page.html))' && has '$P: entry: file old-page.html size=1' && has '$P: entry: dir legacy'"
reset; mk_std pres press; D="$(docroot pres)"; mkdir -p "$D/cgi-bin" "$D/.well-known/pki-validation"; : > "$D/error_log"; : > "$D/.htaccess.before-docroot-guard"; : > "$D/.ftpquota"; run pres:press
check "cgi-bin, .well-known, error_log, .htaccess backups, .ftpquota are ignored by the verdict (still listed)" "v_is $P READY && has '$P: entry: dir cgi-bin' && has '$P: entry: dir .well-known'"
reset; mk_std pres press; D="$(docroot pres)"; for i in $(seq 1 250); do : > "$D/f$i.txt"; done; run pres:press
check "more than 200 entries: the listing stops at 200 and says how many were not listed" "[ \"\$(printf '%s\n' \"\$OUT\" | grep -c '^$P: entry: ')\" = 200 ] && has '$P: docroot_entries: 256' && has '$P: docroot_entries_not_listed: 56'"
reset; mk_std pres press; D="$(docroot pres)"; printf 'x' > "$D/$(printf 'bad\nname')"; printf 'y' > "$D/$(printf 'ctl\001x')"; run pres:press
check "file names with a newline or control character cannot forge output lines" "! printf '%s\n' \"\$OUT\" | grep -vE '^(pres:press: |inventory: |summary: )' | grep -q ."

# ================= 8. layouts: Projects-like, renamed, outside, ambiguous, empty, missing =================
reset; mkdir -p "$(docroot projects)/app" "$(docroot projects)/vendor" "$(docroot projects)/public"; D="$(docroot projects)"
: > "$D/artisan"; printf 'PLANTED_ONE=%s\n' "$SECRET1" > "$D/.env"; : > "$D/public/index.php"; run projects:projects
check "Projects-like (artisan in the docroot): exit 0, reported as docroot-is-app, UNKNOWN-LAYOUT, no failure" "[ $RC -eq 0 ] && has 'projects:projects: app: docroot-is-app' && has 'projects:projects: layout: docroot-is-app' && has 'projects:projects: app_parent_is_docroot: no' && v_is projects:projects 'UNKNOWN-LAYOUT(docroot-is-app)' && has 'summary: READY=0 NEEDS-ATTENTION=0 UNKNOWN-LAYOUT=1 pairs=1'"
check "docroot-is-app: public facts and entries are still reported, no .env content" "has 'projects:projects: public_index_php: yes' && has 'projects:projects: entry: file artisan size=0' && has 'projects:projects: shim: not-applicable' && lacks '$SECRET1'"
reset; D="$(docroot projects)"; mkdir -p "$D/laravel-app/public"; : > "$D/laravel-app/artisan"; : > "$D/laravel-app/public/index.php"; printf '%s\n' "$LARAVEL_HT" > "$D/laravel-app/public/.htaccess"
printf '<?php\nrequire __DIR__.%s;\n' "'/laravel-app/public/index.php'" > "$D/index.php"; run projects:projects
check "app in a folder not named after the key: found by discovery, shim judged against the real folder, flagged" "has 'projects:projects: layout: renamed-folder' && has 'projects:projects: app: ~/projects.infodot.co.za/laravel-app' && has 'projects:projects: app_parent_is_docroot: yes' && has 'projects:projects: shim: standard' && v_is projects:projects 'NEEDS-ATTENTION(app-folder-is-laravel-app-not-projects)'"
reset; D="$(docroot projects)"; mkdir -p "$D" "$HOME/projects/public"; : > "$HOME/projects/artisan"; : > "$HOME/projects/public/index.php"; printf '<?php\n' > "$D/index.php"; run projects:projects
check "app outside the docroot (~/projects): found, reason app-not-inside-docroot" "has 'projects:projects: layout: outside-docroot' && has 'projects:projects: app: ~/projects' && has 'projects:projects: app_parent_is_docroot: no' && v_has projects:projects app-not-inside-docroot"
reset; D="$(docroot projects)"; mkdir -p "$D/a" "$D/b"; : > "$D/a/artisan"; : > "$D/b/artisan"; run projects:projects
check "two artisan files under the docroot and none named after the key: UNKNOWN-LAYOUT listing them" "v_is projects:projects 'UNKNOWN-LAYOUT(multiple-artisan-files(a/artisan,b/artisan))'"
reset; D="$(docroot projects)"; mkdir -p "$D/projects" "$D/other"; : > "$D/projects/artisan"; : > "$D/other/artisan"; mkdir -p "$D/projects/public"; run projects:projects
check "two artisan files but one is in the key's folder: that one wins" "has 'projects:projects: layout: standard'"
reset; mkdir -p "$(docroot pres)"; run pres:press
check "empty docroot: UNKNOWN-LAYOUT(no-laravel-app-found), exit 0" "[ $RC -eq 0 ] && v_is $P 'UNKNOWN-LAYOUT(no-laravel-app-found)' && has '$P: docroot_entries: 0'"
reset; run pres:press
check "no docroot at all: UNKNOWN-LAYOUT(no-docroot), nothing created" "[ $RC -eq 0 ] && has '$P: docroot: ~/pres.infodot.co.za (missing)' && v_is $P 'UNKNOWN-LAYOUT(no-docroot)' && [ ! -e '$HOME/pres.infodot.co.za' ]"
reset; printf 'x' > "$(docroot pres)" ; run pres:press
check "docroot that is a file: UNKNOWN-LAYOUT(no-docroot)" "v_is $P 'UNKNOWN-LAYOUT(no-docroot)'"
reset; mk_std pres press; ln -s nowhere "$(docroot pres)/zz-broken"; : > "$(docroot pres)/press/public/zz-broken-pub"; run pres:press
check "odd: a broken docroot symlink is reported, the run does not stop" "[ $RC -eq 0 ] && has '$P: entry: symlink zz-broken to=nowhere exists=no in_public=no' && v_has $P 'docroot-symlink-broken(zz-broken)'"
reset; mk_std pres press; chmod 000 "$(docroot pres)/press/storage" 2>/dev/null; run pres:press; chmod 755 "$(docroot pres)/press/storage"
check "odd: an unreadable subfolder does not stop the run" "[ $RC -eq 0 ] && has 'summary: READY=1 NEEDS-ATTENTION=0 UNKNOWN-LAYOUT=0 pairs=1'"

# several pairs in one call: verdicts are per pair, one summary
reset; mk_std pres press; mk_std doc doc; : > "$(docroot doc)/doc/public/.htaccess"; rm "$(docroot doc)/index.php"; mkdir -p "$(docroot dotfiles-host)" ; mk_std files dotfiles
run pres:press doc:doc files:dotfiles nohost:nokey
check "four pairs: READY, NEEDS-ATTENTION, READY, UNKNOWN-LAYOUT and the summary counts" "v_is pres:press READY && v_has doc:doc NEEDS-ATTENTION && v_is files:dotfiles READY && v_is nohost:nokey 'UNKNOWN-LAYOUT(no-docroot)' && has 'summary: READY=2 NEEDS-ATTENTION=1 UNKNOWN-LAYOUT=1 pairs=4'"
check "Files' folder under the files host is dotfiles" "has 'files:dotfiles: app: ~/files.infodot.co.za/dotfiles'"
reset; mk_std sheets sheet; mk_std doc doc; run sheets:sheet doc:doc
check "host label differs from the key (sheets:sheet, doc:doc)" "v_is sheets:sheet READY && v_is doc:doc READY"

# ================= 9. cron: counts only, one read, retries, unknown =================
reset; mk_std pres press; mk_std doc doc
{ printf '%s\n' "# pres.infodot.co.za/press commented out $SECRET3" "* * * * * cd $HOME/pres.infodot.co.za/press && php artisan schedule:run # $SECRET3" \
    "*/5 * * * * /usr/bin/php $HOME/pres.infodot.co.za/press/artisan queue:work --stop-when-empty $SECRET3" "0 3 * * * $HOME/pres.infodot.co.za/cleanup.sh $SECRET3" \
    "* * * * * cd $HOME/doc.infodot.co.za/doc && php artisan schedule:run" "MAILTO=$SECRET3"; } > "$CRON_FILE"
run pres:press doc:doc
check "cron: lines mentioning the app folder are counted (comments ignored), never printed" "has '$P: cron_status: read' && has '$P: cron_lines_mentioning_app: 2' && has 'doc:doc: cron_lines_mentioning_app: 1' && lacks '$SECRET3' && lacks schedule:run"
check "cron: a line that mentions the docroot but not the app folder is counted and is a reason" "has '$P: cron_lines_mentioning_docroot_only: 1' && v_has $P 'cron-mentions-docroot-outside-app(1 lines)' && has 'doc:doc: cron_lines_mentioning_docroot_only: 0'"
check "cron: crontab was called once for two pairs, with -l only, and never written" "[ \"\$(cat '$CRON_CALLS')\" = '-l' ] && [ ! -e '$CRON_CALLS.write' ]"
cron_reset
reset; mk_std pres press; run pres:press
check "cron: an empty crontab answer is retried (3 calls) and then reported unknown, never as 0" "has '$P: cron_status: unknown' && has '$P: cron_lines_mentioning_app: unknown' && [ \"\$(wc -l < '$CRON_CALLS' | tr -d ' ')\" = 3 ] && ! printf '%s' \"\$(cat '$CRON_CALLS')\" | grep -qv -- '-l'"
cron_reset; printf '%s\n' "* * * * * cd $HOME/pres.infodot.co.za/press && php artisan schedule:run" > "$CRON_FILE"; export CRON_EMPTY_FIRST=2; run pres:press
check "cron: two empty answers then a good one: read (the transient-empty case)" "has '$P: cron_status: read' && has '$P: cron_lines_mentioning_app: 1' && [ \"\$(wc -l < '$CRON_CALLS' | tr -d ' ')\" = 3 ]"
unset CRON_EMPTY_FIRST; cron_reset
run pres:press doc:doc
check "cron: the read happens once per run even when there are several pairs and no answer" "[ \"\$(wc -l < '$CRON_CALLS' | tr -d ' ')\" = 3 ]"
cron_reset; NOCRON="$W/nocron-bin"; mkdir -p "$NOCRON"; for c in bash env cat grep awk sed tr head find stat readlink du wc sort cut printf sleep dirname basename; do p="$(command -v $c 2>/dev/null)" && [ -n "$p" ] && ln -sf "$p" "$NOCRON/$c"; done
OUT="$(PATH="$NOCRON" bash "$HOST" pres:press 2>&1)"; RC=$?
check "cron: no crontab command on the host: unknown, the rest of the report still works" "[ $RC -eq 0 ] && has '$P: cron_status: unknown' && v_is $P READY"

# ================= 10. NO file contents or secrets in the output (all of it, every scenario above planted some) =================
reset; mk_std pres press; D="$(docroot pres)"
printf '# %s\nSetEnv X %s\n%s\n' "$SECRET2" "$SECRET2" "$SECRET2" >> "$D/.htaccess"; printf '# %s\n' "$SECRET2" >> "$D/press/public/.htaccess"
printf '<?php // %s\n' "$SECRET4" > "$D/index.php"; printf 'k=%s\n' "$SECRET5" > "$D/.user.ini"
printf '%s\n' "* * * * * $HOME/pres.infodot.co.za/press/x $SECRET3" > "$CRON_FILE"
mkdir -p "$D/press/.git"; printf '%s\n' "$SECRET1" > "$D/press/.git/config"
run pres:press doc:doc
for s in "$SECRET1" "$SECRET2" "$SECRET3" "$SECRET4" "$SECRET5" PLANTED_ONE PLANTED_TWO SQLite laravel.log; do check "no leak: '$s' is not in the output" "lacks '$s'"; done
check "no leak: every output line is a header, a summary or a '<pair>: field: value' line" "! printf '%s\n' \"\$OUT\" | grep -vE '^(inventory: |summary: |[a-z0-9-]+:[a-z0-9-]+: [a-z_.]+: )' | grep -q ."

# ================= 11. the summary script =================
reset; mk_std pres press; mk_std doc doc; D="$(docroot doc)"; printf 'AddHandler x .php\n' >> "$D/.htaccess"; ln -s doc/storage/app/public "$D/storage"; run pres:press doc:doc nohost:x
printf '%s\n' "$OUT" > "$W/inv.txt"
S="$(bash "$SUM" "$W/inv.txt")"
check "summary: a row per pair with layout, shim, diff, symlink count, uploads, cron, verdict" "printf '%s\n' \"\$S\" | grep -q '^| pres:press | standard | standard | yes | none | 3 | no | unknown | \*\*READY\*\* |\$' && printf '%s\n' \"\$S\" | grep -q '^| doc:doc | standard | standard | yes | docroot-only AddHandler | 4 | yes (docroot/storage) | unknown | \*\*NEEDS-ATTENTION\*\* |\$' && printf '%s\n' \"\$S\" | grep -q '^| nohost:x | - | - | - | - | 0 | - | - | \*\*UNKNOWN-LAYOUT\*\* |\$'"
check "summary: reasons are listed under the table, the summary line is repeated" "printf '%s\n' \"\$S\" | grep -q '^- doc:doc: \`docroot-htaccess-directives-not-in-public(AddHandler)\`\$' && printf '%s\n' \"\$S\" | grep -q '^summary: READY=1 NEEDS-ATTENTION=1 UNKNOWN-LAYOUT=1 pairs=3\$'"
S="$(bash "$SUM" /nonexistent)"; check "summary: a missing file gives an empty table and a note, exit 0" "printf '%s\n' \"\$S\" | grep -q 'No verdict lines found'"

# ================= 12. READ-ONLY, dynamic: nothing in HOME changes; no temp files; crontab only read =================
snap() { # a checksum listing of a whole tree: type, path, mode, mtime, content checksum or link target
  ( cd "$1" && find . | LC_ALL=C sort | while IFS= read -r p; do
      m="$(stat -c %a "$p" 2>/dev/null || stat -f %Lp "$p")"; t="$(stat -c %Y "$p" 2>/dev/null || stat -f %m "$p")"
      if [ -L "$p" ]; then printf 'L %s %s %s %s\n' "$p" "$m" "$t" "$(readlink "$p")"
      elif [ -f "$p" ]; then printf 'F %s %s %s %s\n' "$p" "$m" "$t" "$(cksum < "$p")"
      else printf 'D %s %s %s\n' "$p" "$m" "$t"; fi
    done )
}
reset
mk_std pres press; mk_std doc doc; mk_std files dotfiles; mk_std sheets sheet
D="$(docroot pres)"; ln -s press/storage/app/public "$D/storage"; printf 'x' > "$D/legacy.html"; mkdir -p "$D/cgi-bin"; : > "$D/.user.ini"
printf 'AddHandler x .php\nHeader set A b\n' >> "$(docroot doc)/.htaccess"
ln -s gone "$(docroot sheets)/dangling"
mkdir -p "$(docroot projects)/app"; : > "$(docroot projects)/artisan"
mkdir -p "$(docroot empty)"
printf '%s\n' "* * * * * cd $HOME/pres.infodot.co.za/press && php artisan schedule:run" > "$CRON_FILE"
sleep 1   # so that a changed mtime could not hide inside the same second
snap "$HOME" > "$W/before"
run pres:press doc:doc files:dotfiles sheets:sheet projects:projects empty:empty nothere:nothere
OUT_NORMAL="$OUT"
snap "$HOME" > "$W/after"
check "read-only: the report ran over seven pairs (verdict lines)" "[ \"\$(printf '%s\n' \"\$OUT\" | grep -c ': verdict: ')\" = 7 ]"
check "read-only: tree checksum (names, modes, mtimes, contents, link targets) identical before and after" "cmp -s '$W/before' '$W/after'"
check "read-only: the tree listing is not trivially empty ($(wc -l < "$W/before" | tr -d ' ') entries)" "[ \$(wc -l < '$W/before') -gt 50 ]"
check "read-only: no file was created in TMPDIR" "[ -z \"\$(ls -A '$TMPDIR')\" ]"
check "read-only: crontab was only ever called with -l" "[ -s '$CRON_CALLS' ] && ! grep -qv -x -- '-l' '$CRON_CALLS' && [ ! -e '$CRON_CALLS.write' ]"
check "read-only: nothing named like a lock, backup, state dir or temp file appeared in HOME" "! find '$HOME' -maxdepth 1 -name '.dot-*' | grep -q . && ! find '$HOME' -name '*.new.*' -o -name '*.lock' -o -name '*.tmp' | grep -q ."
if [ "$(id -u)" != 0 ]; then
  chmod -R a-w "$HOME"
  run pres:press doc:doc files:dotfiles sheets:sheet projects:projects empty:empty nothere:nothere
  chmod -R u+w "$HOME"
  check "read-only: on a HOME with every write permission removed the report is the same" "[ \"\$OUT\" = \"\$OUT_NORMAL\" ]"
else
  ok "read-only: (skipped the unwritable-HOME run: running as root)"
fi
run pres:press doc:doc files:dotfiles sheets:sheet projects:projects empty:empty nothere:nothere
check "report is stable: two runs on the same tree give the same output" "[ \"\$OUT\" = \"\$OUT_NORMAL\" ]"

# ================= 13. wiring in the workflow and in CI =================
WF="$ROOT/.github/workflows/app-folder-guard.yml"
check "workflow: docroot-inventory is a mode option" "grep -q 'options: \[check, apply, docroot-check, docroot, docroot-inventory\]' '$WF'"
STEP="$(awk '/^      - name: Inventory/ {p = 1; print; next} p && /^      - name: / {exit} p {print}' "$WF")"
check "workflow: the inventory step pipes only tools/docroot-inventory-host.sh over ssh, for the docroot-inventory mode" "printf '%s\n' \"\$STEP\" | grep -q \"inputs.mode == 'docroot-inventory'\" && printf '%s\n' \"\$STEP\" | grep -q 'tools/docroot-inventory-host.sh' && ! printf '%s\n' \"\$STEP\" | grep -q 'docroot-guard-host.sh'"
check "workflow: the inventory step has no write verb in its command" "! printf '%s\n' \"\$STEP\" | grep -Eq 'apply|write|undo|rm |mv |chmod|crontab'"
check "workflow: the SSH set-up step covers the new mode" "grep -A3 'name: Set up SSH' '$WF' | grep -q \"inputs.mode == 'docroot-inventory'\""
check "workflow: the raw output is uploaded as an artifact and the summary script is used" "grep -q 'actions/upload-artifact@v4' '$WF' && grep -q 'tools/docroot-inventory-summary.sh' '$WF'"
check "CI: ci.yml runs shellcheck and this test in a fake-host job" "grep -q 'tools/test-docroot-inventory.sh' '$ROOT/.github/workflows/ci.yml' && grep -q 'shellcheck -S warning tools/docroot-inventory-host.sh' '$ROOT/.github/workflows/ci.yml'"

echo; echo "$N checks, $( [ "$FAIL" = 0 ] && echo 'all passed' || echo 'SOME FAILED' )"
exit "$FAIL"
