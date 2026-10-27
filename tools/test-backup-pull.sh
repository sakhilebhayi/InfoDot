#!/usr/bin/env bash
# Tests of the off-host pull: tools/backup-pull-serve.sh (the forced command on the host: read-only rsync of ~/dot-backups,
# nothing else) and tools/pull-dot-backups.sh (the Mac side). A shim rsync stands in for the real one; no network, no SSH.
set -uo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
W="$(mktemp -d)"; trap 'rm -rf "$W"' EXIT
FAIL=0; N=0
ok() { N=$((N + 1)); echo "ok   - $1"; }
bad() { N=$((N + 1)); echo "FAIL - $1"; FAIL=1; }
check() { if eval "$2"; then ok "$1"; else bad "$1"; fi; }

# ---- host side: the forced command ---------------------------------------------------------------------------------
mkdir -p "$W/bin" "$W/home"; printf '#!/bin/sh\necho "RSYNC-RAN: $*"\n' > "$W/bin/rsync"; chmod +x "$W/bin/rsync"
serve() { HOME="$W/home" PATH="$W/bin:$PATH" SSH_ORIGINAL_COMMAND="$1" sh "$ROOT/tools/backup-pull-serve.sh" 2>&1; }
for c in 'rsync --server --sender -g -l -n -o -p -D -r -t -v --dirs . dot-backups/' 'rsync --server --sender -g -l -o -p -D -r -t -v --dirs . dot-backups/' 'rsync --server --sender -logDtpre.iLsfxCIvu . dot-backups/' 'rsync --server --sender -vvlogDtpre.iLsfxCIvu . dot-backups'; do
  check "allowed: $c" "serve '$c' | grep -q 'RSYNC-RAN: --server --sender'"
done
for c in 'cat /etc/passwd' 'rsync --server --sender -a . /etc' 'rsync --server --sender -a . ../' 'rsync --server --sender -a . dot-backups/../.ssh' 'rsync --server -a . dot-backups/' 'rsync --server --sender --rsync-path=x -a . dot-backups/' 'rsync --server --sender --delete -a . dot-backups/' 'rsync --server --sender -a;id . dot-backups/' 'rsync --server --sender -a $(id) . dot-backups/' '' 'rsync --server --sender -a . .'; do
  check "denied: ${c:-<empty>}" "[ \"\$(serve '$c')\" = denied ]"
done
check "a refusal is logged with the command (no secrets in it)" "grep -q 'rsync --server --sender -a . /etc' '$W/home/backup-pull-denied.log'"

# ---- Mac side: the pull script, against a local folder -------------------------------------------------------------
S="$W/src"; mkdir -p "$S/alpha" "$S/beta"
for a in alpha beta; do f="$a-20261027-010101.tar.gz.enc"; echo "data-$a" > "$S/$a/$f"; (cd "$S/$a" && shasum -a 256 "$f" | awk -v f="$f" '{print $1"  "f}' > "$f.sha256"); done
pull() { SRC="$S/" DEST="$W/dest" LOG="$W/pull.log" NOTIFY=0 "$@" bash "$ROOT/tools/pull-dot-backups.sh" >/dev/null 2>&1; }
pull env MAX_AGE_H=100000; check "good pull: exit 0, both apps checked" "[ $? = 0 ] && tail -1 '$W/pull.log' | grep -q '2 apps checked, 0 problem'"
echo tampered > "$S/beta/beta-20261027-010101.tar.gz.enc"; pull env MAX_AGE_H=100000
check "a copy that does not match its checksum is flagged (exit 1, ALERT line)" "! pull env MAX_AGE_H=100000 && grep -q 'ALERT: beta: checksum mismatch' '$W/pull.log'"
echo data-beta > "$S/beta/beta-20261027-010101.tar.gz.enc"; touch -t 202610010000 "$S/alpha/alpha-20261027-010101.tar.gz.enc"
check "a stale newest archive is flagged" "! pull env MAX_AGE_H=36 && grep -q 'ALERT: alpha: newest archive is' '$W/pull.log'"
rm -rf "$S/alpha"; pull env MAX_AGE_H=100000000
check "no --delete: an app removed on the host stays in the local copy" "[ -d '$W/dest/alpha' ]"
check "rsync failure is an alert and exit 1" "! SRC=/nonexistent/ DEST='$W/dest2' LOG='$W/pull.log' NOTIFY=0 bash '$ROOT/tools/pull-dot-backups.sh' >/dev/null 2>&1 && grep -q 'ALERT: rsync failed' '$W/pull.log'"
mkdir "$W/.pull.lock"; check "a second pull while one runs is skipped" "pull env MAX_AGE_H=100000000 && tail -1 '$W/pull.log' | grep -q 'another pull is running'"

echo; echo "$N checks, $( [ "$FAIL" = 0 ] && echo 'all passed' || echo 'SOME FAILED' )"
exit "$FAIL"
