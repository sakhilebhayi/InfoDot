#!/usr/bin/env bash
# Runs ON the host (piped over ssh by .github/workflows/app-folder-guard.yml, modes docroot / docroot-check) and in
# tools/test-docroot-guard.sh against a temp HOME. The tested code is the deployed code.
#
#   docroot-guard-host.sh write <host>:<key> [...]   put/refresh the guard block at the TOP of ~/<host>.infodot.co.za/.htaccess
#   docroot-guard-host.sh plan  <host>:<key> [...]   same decision, writes nothing (what `write` WOULD do)
#   docroot-guard-host.sh undo  <host>:<key> [...]   put the .htaccess back as it was before this pair's last `write`
#
# Why: the app folder (~/<host>.infodot.co.za/<key>/) sits INSIDE the docroot and is protected by its own `.htaccess`
# (Require all denied). On 2026-10-22 that file was not applied for a few seconds after a deploy on two apps. The
# docroot's `.htaccess` is never touched by a deploy, so a narrow rule there is a second layer outside the deployed tree.
#
# The block, between the markers, is exactly:
#   # BEGIN dot-app-folder-guard <key>
#   <IfModule mod_rewrite.c>
#   RewriteEngine On
#   RewriteRule ^<key>/(\.env.*|\.git.*|artisan|...|node_modules/) - [F,L,NC]
#   </IfModule>
#   # END dot-app-folder-guard <key>
#
# Rules: block goes at the very top (nothing can shadow it); an existing block is replaced in place; a missing file
# is created holding only the block; every byte outside the markers is left as it was (CRLF files keep CRLF, a missing
# final newline stays missing); a one-time backup .htaccess.before-docroot-guard is made the first time and never
# overwritten; before any change the previous file is kept in ~/.dot-docroot-guard/ (outside the docroot) for `undo`.
# Prints one line per pair and never prints file contents. Exit: 0 ok, 2 bad input (nothing touched), 3 a pair was
# skipped or refused (write/undo) or a file has unbalanced markers.
set -uo pipefail

BASE="${BASE_DOMAIN:-infodot.co.za}"
STATE="$HOME/.dot-docroot-guard"
BACKUP_NAME=".htaccess.before-docroot-guard"
MARK="dot-app-folder-guard"
ACTION="${1:-}"; [ $# -gt 0 ] && shift
case "$ACTION" in write|plan|undo) ;; *) echo "usage: $0 write|plan|undo host:key [...]" >&2; exit 2 ;; esac
[ $# -gt 0 ] || { echo "give at least one host:key pair" >&2; exit 2; }
for pair in "$@"; do
  printf '%s' "$pair" | grep -Eq '^[a-z0-9-]+:[a-z0-9-]+$' || { echo "bad pair [$pair]: expected host:key, each [a-z0-9-]+" >&2; exit 2; }
done

TMPS=""
cleanup() { for t in $TMPS; do rm -f "$t"; done; }
trap cleanup EXIT
newtmp() { local t; t="$(mktemp "${TMPDIR:-/tmp}/docroot-guard.XXXXXX")"; TMPS="$TMPS $t"; echo "$t"; }

# The block for a key, LF separated, no trailing newline. The key was validated as [a-z0-9-]+ above.
block_text() {
  local k="$1"
  printf '%s\n' "# BEGIN $MARK $k" '<IfModule mod_rewrite.c>' 'RewriteEngine On'
  printf '%s\n' 'RewriteRule ^'"$k"'/(\.env.*|\.git.*|artisan|composer\.(json|lock)|package(-lock)?\.json|phpunit\.xml.*|database/|storage/|vendor/|bootstrap/|config/|tests/|node_modules/) - [F,L,NC]'
  printf '%s' '</IfModule>'; printf '\n'; printf '%s' "# END $MARK $k"
}

# Reads the .htaccess on stdin, writes the new one on stdout. Env: GUARD_BLOCK (LF separated), GUARD_FINAL_NL (1/0).
# Marker lines are compared whole (a CR at the end of a line is ignored), so `dot` never matches `dotfiles`.
# The first block is replaced in place (CRLF kept if that line had it); a repeated block is dropped; none -> block on top.
# Exit 3 if a BEGIN has no END (nothing is printed: the caller keeps the original).
AWK_PROG='
BEGIN { nb = split(ENVIRON["GUARD_BLOCK"], B, "\n"); beginm = B[1]; endm = B[nb]; fnl = ENVIRON["GUARD_FINAL_NL"] }
{ n++; L[n] = $0 }
function emit(cr,   j) { for (j = 1; j <= nb; j++) out[++m] = B[j] (cr ? "\r" : "") }
END {
  inblk = 0; placed = 0; m = 0
  for (i = 1; i <= n; i++) {
    t = L[i]; cr = sub(/\r$/, "", t)
    if (inblk) { if (t == endm) inblk = 0; continue }
    if (t == beginm) { inblk = 1; if (!placed) { emit(cr); placed = 1 } ; continue }
    out[++m] = L[i]
  }
  if (inblk) { print "unbalanced markers" > "/dev/stderr"; exit 3 }
  if (!placed) {
    # block on top, in the line ending style of the first line of the file
    cr = (n >= 1 && L[1] ~ /\r$/) ? 1 : 0
    k = m; for (i = 1; i <= k; i++) rest[i] = out[i]
    m = 0; emit(cr); for (i = 1; i <= k; i++) out[++m] = rest[i]
  }
  for (i = 1; i <= m; i++) { printf "%s", out[i]; if (i < m || fnl == "1") printf "\n" }
}'

file_mode() { stat -c %a "$1" 2>/dev/null || stat -f %Lp "$1" 2>/dev/null || echo 644; }

RC=0
note() { # $1 = pair, rest = text
  local p="$1"; shift; echo "$p: $*"
}

do_pair() { # $1 = action, $2 = pair
  local action="$1" pair="$2" block host key docroot f cand cur existed result bk fnl mode snap abs
  host="${pair%%:*}"; key="${pair##*:}"
  docroot="$HOME/$host.$BASE"; f="$docroot/.htaccess"
  snap="$STATE/$host.$key.undo"; abs="$STATE/$host.$key.absent"

  if [ "$action" = undo ]; then
    if [ -f "$snap" ]; then
      cur="$docroot/.htaccess.new.$$"; TMPS="$TMPS $cur"
      cp -p "$snap" "$cur" && mv -f "$cur" "$f" && rm -f "$snap" "$abs" \
        && note "$pair" "undone (previous .htaccess restored)" || { note "$pair" "ERROR restoring"; RC=3; }
    elif [ -f "$abs" ]; then
      rm -f "$f" "$abs"; note "$pair" "undone (there was no .htaccess before; removed)"
    else
      note "$pair" "nothing-to-undo"
    fi
    return
  fi

  if [ ! -d "$docroot" ]; then note "$pair" "skipped (no docroot ~/$host.$BASE)"; [ "$action" = write ] && RC=3; return; fi
  if [ ! -f "$docroot/$key/artisan" ]; then note "$pair" "skipped (no Laravel app at ~/$host.$BASE/$key)"; [ "$action" = write ] && RC=3; return; fi
  if [ -L "$f" ]; then note "$pair" "refused (.htaccess is a symlink)"; RC=3; return; fi
  if [ -e "$f" ] && [ ! -f "$f" ]; then note "$pair" "refused (.htaccess is not a regular file)"; RC=3; return; fi

  cand="$(newtmp)"
  block="$(block_text "$key")"
  if [ -f "$f" ]; then
    existed=0
    grep -qxF -- "# BEGIN $MARK $key" "$f" && existed=1
    grep -qxF -- "# BEGIN $MARK $key$(printf '\r')" "$f" && existed=1
    fnl=1; [ -n "$(tail -c1 "$f")" ] && fnl=0
    if ! GUARD_BLOCK="$block" GUARD_FINAL_NL="$fnl" awk "$AWK_PROG" < "$f" > "$cand" 2>/dev/null; then
      note "$pair" "refused (BEGIN $MARK $key without a matching END; file untouched)"; RC=3; return
    fi
    if cmp -s "$f" "$cand"; then result="unchanged"
    elif [ "$existed" = 1 ]; then result="replaced"
    else result="written (prepended)"; fi
  else
    printf '%s\n' "$block" > "$cand"; result="written (new file)"
  fi

  if [ "$action" = plan ]; then
    case "$result" in unchanged) ;; replaced) result="would-replace" ;; *) result="would-write${result#written}" ;; esac
    note "$pair" "$result"; return
  fi

  # write: stale undo state from an earlier run must never be reused
  rm -f "$snap" "$abs"
  if [ "$result" = unchanged ]; then
    note "$pair" "unchanged backup=$( [ -e "$docroot/$BACKUP_NAME" ] && echo kept || echo none )"; return
  fi
  ( umask 077; mkdir -p "$STATE" ) || { note "$pair" "ERROR cannot create $STATE"; RC=3; return; }
  if [ -f "$f" ]; then cp -p "$f" "$snap"; else : > "$abs"; fi

  bk=none
  if [ -f "$f" ]; then
    if [ -e "$docroot/$BACKUP_NAME" ]; then bk=kept
    elif ! grep -q "BEGIN $MARK " "$f"; then cp -p "$f" "$docroot/$BACKUP_NAME" && bk=created
    fi
  fi

  mode=644; [ -f "$f" ] && mode="$(file_mode "$f")"
  cur="$docroot/.htaccess.new.$$"; TMPS="$TMPS $cur"
  cp "$cand" "$cur" && chmod "$mode" "$cur" && mv -f "$cur" "$f" || { note "$pair" "ERROR writing .htaccess"; RC=3; return; }
  if ! cmp -s "$cand" "$f"; then note "$pair" "ERROR read-back differs"; RC=3; return; fi
  note "$pair" "$result backup=$bk"
}

if [ "$ACTION" = undo ]; then
  # reverse order, so two pairs on one docroot unwind last-in first
  pairs=(); for p in "$@"; do pairs=("$p" ${pairs[@]+"${pairs[@]}"}); done
  for p in "${pairs[@]}"; do do_pair undo "$p"; done
else
  for p in "$@"; do do_pair "$ACTION" "$p"; done
fi
exit "$RC"
