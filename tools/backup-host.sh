#!/usr/bin/env bash
# Backs up every Laravel app on the shared cPanel host: database, .env and user uploads, one encrypted archive per
# app per run. Runs ON the host (installed as ~/bin/dot-backup by .github/workflows/backups.yml), needs no network
# access, and never reads or prints a secret: .env values are parsed (never sourced) and only used to reach the
# database; output is counts, sizes, names and seconds. Full description: docs/programme/BACKUPS.md.
#
#   dot-backup init                              create ~/dot-backups, ~/.dot-backup and the passphrase file (once)
#   dot-backup inventory [--strict]              read-only: what exists, what is backed up, what is stale or flagged
#   dot-backup run [key ...]                     back up every app (or the named ones); retention is applied after
#   dot-backup verify [key ...]                  decrypt the newest archive to scratch, check it, delete the scratch
#   dot-backup restore <key> <archive> <dir>     decrypt + extract into a NEW scratch directory (never a live app)
#   dot-backup restore-test [key]                restore + integrity check + timings, then remove the scratch copy
#   dot-backup prune [key ...]                   apply the retention rule (newest N + one per month)
#   dot-backup schedule | unschedule             add / remove ONE nightly cron line (flocked, read-retried, verified)
#   Flag: --time prints elapsed seconds per step (the RTO measurement).
#
# Environment (all optional): DOT_BACKUP_KEEP (14), DOT_BACKUP_KEEP_MONTHS (6), DOT_BACKUP_PARALLEL (1),
# DOT_BACKUP_TIMEOUT per app in seconds (900), DOT_BACKUP_UPLOADS_MAX_MB (200), DOT_BACKUP_OFFSITE_TIMEOUT (1800),
# DOT_BACKUP_PASSFILE (alternative passphrase file, e.g. when restoring on another machine), BASE_DOMAIN
# (infodot.co.za), PHP_BIN (php). Test/diagnostic knobs: DOT_BACKUP_SQLITE_TOOL=auto|sqlite3|php,
# DOT_BACKUP_NOW_YM=YYYYMM (clock for the monthly retention rule).
set -u -o pipefail
umask 077

BASE="${BASE_DOMAIN:-infodot.co.za}"
CONF="$HOME/.dot-backup"
PASSFILE="${DOT_BACKUP_PASSFILE:-$CONF/passphrase}"
HOOK="$CONF/offsite.sh"
EXTRA="$CONF/apps.list"
ROOT="$HOME/dot-backups"
MANIFEST="$ROOT/manifest.log"
BIN="$HOME/bin/dot-backup"
KEEP="${DOT_BACKUP_KEEP:-14}"
KEEP_MONTHS="${DOT_BACKUP_KEEP_MONTHS:-6}"
PAR="${DOT_BACKUP_PARALLEL:-1}"
APP_TIMEOUT="${DOT_BACKUP_TIMEOUT:-900}"
UPLOADS_MAX_MB="${DOT_BACKUP_UPLOADS_MAX_MB:-200}"
PHP="${PHP_BIN:-php}"
STALE_SECONDS=$((36 * 3600))
ITER=200000
TIME="${DOT_BACKUP_TIME:-0}"
STRICT=0
case "${BASH_SOURCE[0]}" in /*) SELF="${BASH_SOURCE[0]}" ;; *) SELF="$PWD/${BASH_SOURCE[0]}" ;; esac

# cron starts with a minimal PATH; add the places php and the database clients live on cPanel hosts.
for p in /usr/local/bin /usr/bin /bin /usr/local/cpanel/3rdparty/bin /opt/cpanel/composer/bin; do
  case ":$PATH:" in *":$p:"*) ;; *) [ -d "$p" ] && PATH="$PATH:$p" ;; esac
done
export PATH

say() { printf '%s\n' "$*"; }
err() { printf '%s\n' "$*" >&2; }
is_int() { case "${1:-}" in ''|*[!0-9]*) return 1 ;; *) return 0 ;; esac; }
file_bytes() { wc -c < "$1" | tr -d ' '; }
has() { command -v "$1" >/dev/null 2>&1; }

usage() { sed -n '2,/^set -u/p' "$SELF" | sed '$d' | sed 's/^# \{0,1\}//' ; }

# ---------------------------------------------------------------------------------------------------- timing
now_ms() {
  local n; n="$(date +%s%N 2>/dev/null)"
  case "$n" in
    ''|*[!0-9]*) echo $(( $(date +%s) * 1000 )) ;;
    *) if [ "${#n}" -gt 13 ]; then echo $(( n / 1000000 )); else echo $(( n * 1000 )); fi ;;
  esac
}
ms_fmt() { printf '%d.%02d' $(( $1 / 1000 )) $(( ($1 % 1000) / 10 )); }
T_LAST=0
tstart() { T_LAST="$(now_ms)"; }
tstep() { # label [prefix]
  local n; n="$(now_ms)"
  [ "$TIME" = 1 ] && printf '  [time] %s%-22s %s s\n' "${2:+$2 }" "$1" "$(ms_fmt $(( n - T_LAST )))"
  T_LAST="$n"
  return 0
}

# ---------------------------------------------------------------------------------------------------- helpers
hsize() { awk -v b="$1" 'BEGIN{ if (b<1024) printf "%dB", b; else if (b<1048576) printf "%.1fK", b/1024; else if (b<1073741824) printf "%.1fM", b/1048576; else printf "%.2fG", b/1073741824 }'; }
hage() { local s="$1"; if [ "$s" -lt 7200 ]; then echo "$(( s / 60 ))m"; elif [ "$s" -lt 172800 ]; then echo "$(( s / 3600 ))h"; else echo "$(( s / 86400 ))d"; fi; }

sha256_of() { # hex digest of a file
  if has sha256sum; then sha256sum "$1" | cut -d' ' -f1
  elif has shasum; then shasum -a 256 "$1" | cut -d' ' -f1
  else openssl dgst -sha256 -r "$1" | cut -d' ' -f1; fi
}

stamp_epoch() { # YYYYmmdd-HHMMSS (UTC) -> epoch seconds
  local s="$1" e
  if e="$(date -u -d "${s:0:4}-${s:4:2}-${s:6:2} ${s:9:2}:${s:11:2}:${s:13:2}" +%s 2>/dev/null)"; then echo "$e"; return 0; fi
  date -u -j -f '%Y%m%d-%H%M%S' "$s" +%s 2>/dev/null
}

# envget NAME FILE: the value of the last NAME=... line, unquoted. Parsed, never sourced; ${VAR} interpolation is not expanded.
envget() {
  local name="$1" file="$2" line val out c i
  line="$(grep -E "^[[:space:]]*(export[[:space:]]+)?${name}[[:space:]]*=" "$file" 2>/dev/null | tail -n 1)" || true
  [ -n "$line" ] || return 1
  line="${line%$'\r'}"
  val="${line#*=}"
  val="${val#"${val%%[![:space:]]*}"}"
  case "$val" in
    \"*)
      val="${val#\"}"; out=""; i=0
      while [ "$i" -lt "${#val}" ]; do
        c="${val:$i:1}"
        if [ "$c" = '\' ] && [ $(( i + 1 )) -lt "${#val}" ]; then
          case "${val:$((i+1)):1}" in '"'|'\') out="$out${val:$((i+1)):1}"; i=$(( i + 2 )); continue ;; esac
        fi
        [ "$c" = '"' ] && break
        out="$out$c"; i=$(( i + 1 ))
      done
      printf '%s' "$out" ;;
    \'*) val="${val#\'}"; printf '%s' "${val%%\'*}" ;;
    *) val="${val%%[[:space:]]#*}"; printf '%s' "${val%"${val##*[![:space:]]}"}" ;;
  esac
}

# ---------------------------------------------------------------------------------------------------- crypto
mode_is_600() { [ "$(ls -ld "$1" 2>/dev/null | cut -c1-10)" = "-rw-------" ]; }
pass_ok() {
  [ -f "$PASSFILE" ] && [ ! -L "$PASSFILE" ] || { err "refusing: passphrase file missing ($PASSFILE); run: dot-backup init"; return 1; }
  mode_is_600 "$PASSFILE" || { err "refusing: passphrase file mode is not 600 (chmod 600 $PASSFILE)"; return 1; }
  [ -s "$PASSFILE" ] || { err "refusing: passphrase file is empty ($PASSFILE)"; return 1; }
}
ossl_enc() { openssl enc -aes-256-cbc -pbkdf2 -iter "$ITER" -md sha256 -salt -pass "file:$PASSFILE" "$@"; }
ossl_dec() { openssl enc -d -aes-256-cbc -pbkdf2 -iter "$ITER" -md sha256 -pass "file:$PASSFILE" "$@"; }

need_tools() { # $1 = what for; the tools every backup/restore needs
  local t miss=""
  for t in openssl tar gzip awk; do has "$t" || miss="$miss $t"; done
  has sha256sum || has shasum || has openssl || miss="$miss sha256"
  if [ "$1" = run ] || [ "$1" = prune ] || [ "$1" = schedule ]; then has flock || miss="$miss flock"; fi
  [ -z "$miss" ] || { err "missing on this host:$miss"; return 1; }
}

# ---------------------------------------------------------------------------------------------------- discovery
# Prints "key<TAB>folder<TAB>state" (state ok | none | ambiguous), one line per host docroot. The host label is not
# always the key (pres -> press, sheets -> sheet, doc -> docs) and the app folder is not always named after it (Files
# lives in "dotfiles"), so the single `artisan` under the docroot (two levels at most) is the app, as in
# tools/apply-secrets.sh. ~/.dot-backup/apps.list ("key /absolute/path" per line) overrides or adds apps.
emit_for() { # key docroot
  local found n
  found="$(find "$2" -maxdepth 2 -name artisan -type f 2>/dev/null | sort)"
  n="$(printf '%s\n' "$found" | grep -c . || true)"
  if [ "$n" = 1 ]; then printf '%s\t%s\tok\n' "$1" "${found%/artisan}"
  elif [ "$n" = 0 ]; then printf '%s\t%s\tnone\n' "$1" "$2"
  else printf '%s\t%s\tambiguous\n' "$1" "$2"; fi
}
discover() {
  local d label key k p
  {
    for d in "$HOME"/*."$BASE"; do
      [ -d "$d" ] || continue
      label="${d##*/}"; label="${label%."$BASE"}"
      case "$label" in pres) key=press ;; sheets) key=sheet ;; doc) key=docs ;; *) key="$label" ;; esac
      case "$key" in *[!a-z0-9-]*|'') continue ;; esac
      emit_for "$key" "$d"
    done
    if [ ! -d "$HOME/infodot.$BASE" ] && [ -d "$HOME/public_html" ]; then emit_for infodot "$HOME/public_html"; fi
    if [ -f "$EXTRA" ]; then
      while read -r k p; do
        case "$k" in ''|'#'*) continue ;; esac
        case "$k" in *[!a-z0-9-]*) continue ;; esac
        case "$p" in "$HOME"/*) ;; *) continue ;; esac
        if [ -f "$p/artisan" ]; then printf '%s\t%s\tok\n' "$k" "$p"; else printf '%s\t%s\tnone\n' "$k" "$p"; fi
      done < "$EXTRA"
    fi
  } | awk -F'\t' '{ m[$1] = $0 } END { for (k in m) print m[k] }' | sort
}

# resolve_db APPDIR: sets D_DRIVER (sqlite|mysql|pgsql|none|noenv|<unsupported name>) and the connection settings.
D_DRIVER=""; D_NAME=""; D_HOST=""; D_PORT=""; D_USER=""; D_PASS=""; D_SOCK=""
resolve_db() {
  local app="$1" env="$1/.env" drv
  D_DRIVER=""; D_NAME=""; D_HOST=""; D_PORT=""; D_USER=""; D_PASS=""; D_SOCK=""
  [ -f "$env" ] || { D_DRIVER=noenv; return 0; }
  drv="$(envget DB_CONNECTION "$env")" || drv=""
  if [ -z "$drv" ]; then if [ -f "$app/database/database.sqlite" ]; then drv=sqlite; else drv=none; fi; fi
  case "$drv" in
    null) drv=none ;;
    mysql|mariadb) drv=mysql ;;
    pgsql|postgres|postgresql) drv=pgsql ;;
  esac
  D_DRIVER="$drv"
  D_NAME="$(envget DB_DATABASE "$env")" || D_NAME=""
  D_HOST="$(envget DB_HOST "$env")" || D_HOST=""
  D_PORT="$(envget DB_PORT "$env")" || D_PORT=""
  D_USER="$(envget DB_USERNAME "$env")" || D_USER=""
  D_PASS="$(envget DB_PASSWORD "$env")" || D_PASS=""
  D_SOCK="$(envget DB_SOCKET "$env")" || D_SOCK=""
  [ -n "$D_HOST" ] || D_HOST=127.0.0.1
  if [ -z "$D_PORT" ]; then if [ "$drv" = pgsql ]; then D_PORT=5432; else D_PORT=3306; fi; fi
  return 0
}
sqlite_path() { # APPDIR -> the SQLite file the app uses
  local app="$1" p
  case "$D_NAME" in
    ''|:memory:) p="$app/database/database.sqlite" ;;
    /*) p="$D_NAME" ;;
    *) p="$app/$D_NAME"; [ -f "$p" ] || p="$app/database/$D_NAME" ;;
  esac
  printf '%s' "$p"
}

# ---------------------------------------------------------------------------------------------------- sqlite
# The only ways this tool reads a live SQLite file: the online-backup API (sqlite3 .backup, or PHP's
# SQLite3::backup, or VACUUM INTO where PHP lacks backup()). If none exists it refuses; it never `cp`s a live file.
REASON=""
sqlite_snapshot() { # src dst   (needs $W for scratch files)
  local src="$1" dst="$2" tool="${DOT_BACKUP_SQLITE_TOOL:-auto}"
  if [ "$tool" != php ] && has sqlite3; then
    case "$src$dst" in *\'*) REASON=unsafe_path; return 1 ;; esac
    if ! sqlite3 "$src" ".timeout 30000" ".backup '$dst'" >/dev/null 2>"$W/err"; then REASON=sqlite_backup_failed; DETAIL="$(head -n 1 "$W/err" 2>/dev/null)"; return 1; fi
    [ -s "$dst" ] || { REASON=sqlite_backup_empty; return 1; }
    return 0
  fi
  if [ "$tool" != sqlite3 ] && has "$PHP"; then
    cat > "$W/snap.php" <<'PHP'
<?php
$s = new SQLite3(getenv('DOT_SRC')); $s->busyTimeout(30000);
if (method_exists($s, 'backup')) { $d = new SQLite3(getenv('DOT_DST')); $ok = $s->backup($d); $d->close(); }
else { $ok = $s->exec('VACUUM INTO ' . chr(39) . SQLite3::escapeString(getenv('DOT_DST')) . chr(39)); }
$s->close(); exit($ok ? 0 : 3);
PHP
    if ! DOT_SRC="$src" DOT_DST="$dst" "$PHP" "$W/snap.php" >/dev/null 2>"$W/err"; then REASON=php_sqlite_backup_failed; DETAIL="$(head -n 1 "$W/err" 2>/dev/null)"; return 1; fi
    [ -s "$dst" ] || { REASON=sqlite_backup_empty; return 1; }
    return 0
  fi
  REASON=no_sqlite_tool; return 1
}
# sqlite_check FILE: prints "integrity=ok tables=N rows=M" (counts only); returns 1 unless integrity_check says ok.
sqlite_check() {
  local f="$1" tool="${DOT_BACKUP_SQLITE_TOOL:-auto}" ic t tn n tables=0 rows=0 names out chk
  if [ "$tool" != php ] && has sqlite3; then
    ic="$(sqlite3 "$f" 'PRAGMA integrity_check;' 2>/dev/null | head -n 1)" || true
    if [ "$ic" != ok ]; then echo "integrity=FAILED tables=? rows=?"; return 1; fi
    names="$(sqlite3 "$f" "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%';" 2>/dev/null)" || names=""
    while IFS= read -r t; do
      [ -n "$t" ] || continue
      tables=$(( tables + 1 )); tn="${t//\"/\"\"}"
      n="$(sqlite3 "$f" "SELECT count(*) FROM \"$tn\";" 2>/dev/null)" || n=0
      is_int "$n" || n=0
      rows=$(( rows + n ))
    done <<< "$names"
    echo "integrity=ok tables=$tables rows=$rows"; return 0
  fi
  if [ "$tool" != sqlite3 ] && has "$PHP"; then
    chk="$(mktemp)"
    cat > "$chk" <<'PHP'
<?php
$s = new SQLite3(getenv('DOT_SRC'));
$ic = $s->querySingle('PRAGMA integrity_check'); $t = 0; $r = 0;
$q = $s->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
while ($x = $q->fetchArray(SQLITE3_NUM)) { $t++; $r += (int)$s->querySingle('SELECT count(*) FROM "' . str_replace('"', '""', $x[0]) . '"'); }
echo $ic . '|' . $t . '|' . $r;
PHP
    out="$(DOT_SRC="$f" "$PHP" "$chk" 2>/dev/null)" || out=""; rm -f "$chk"
    if [ "${out%%|*}" = ok ]; then echo "integrity=ok tables=$(echo "$out" | cut -d'|' -f2) rows=$(echo "$out" | cut -d'|' -f3)"; return 0; fi
    echo "integrity=FAILED tables=? rows=?"; return 1
  fi
  echo "integrity=UNCHECKED (no sqlite3 or php on this host)"; return 1
}

# ---------------------------------------------------------------------------------------------------- mysql / postgres
dump_footer_ok() { # the last 4 KB of a dump hold mysqldump's or pg_dump's "finished" comment
  local t; t="$(tail -c 4096 "$1")"
  case "$t" in *"Dump completed"*|*"PostgreSQL database dump complete"*) return 0 ;; esac
  return 1
}
redact() { # redact one line of tool output: never echo the password back
  local l="$1"
  [ -n "$D_PASS" ] && l="${l//"$D_PASS"/***}"
  printf '%s' "${l:0:160}"
}
cnf_esc() { local s="$1"; s="${s//\\/\\\\}"; printf '%s' "${s//\"/\\\"}"; }
pgpass_esc() { local s="$1"; s="${s//\\/\\\\}"; printf '%s' "${s//:/\\:}"; }

mysql_dump() { # dst   (credentials only via a mode-600 defaults file, removed by the caller's cleanup)
  local dst="$1" cnf="$W/my.cnf" extra="" hlp
  has mysqldump || { REASON=no_mysqldump; return 1; }
  case "$D_USER$D_PASS$D_HOST" in *$'\n'*) REASON=bad_credentials; return 1 ;; esac
  {
    printf '[client]\n'
    printf 'user="%s"\n' "$(cnf_esc "$D_USER")"
    printf 'password="%s"\n' "$(cnf_esc "$D_PASS")"
    if [ -n "$D_SOCK" ]; then printf 'socket="%s"\n' "$(cnf_esc "$D_SOCK")"
    else printf 'host="%s"\nport=%s\n' "$(cnf_esc "$D_HOST")" "$D_PORT"; fi
  } > "$cnf"
  chmod 600 "$cnf"
  hlp="$(mysqldump --help 2>/dev/null)"; case "$hlp" in *no-tablespaces*) extra="--no-tablespaces" ;; esac
  # shellcheck disable=SC2086
  if ! mysqldump --defaults-extra-file="$cnf" --single-transaction --quick $extra "$D_NAME" > "$dst" 2>"$W/err"; then
    REASON=mysqldump_failed; DETAIL="$(head -n 1 "$W/err" 2>/dev/null)"; return 1
  fi
  dump_footer_ok "$dst" || { REASON=dump_incomplete; return 1; }
  return 0
}
pg_dump_to() { # dst
  local dst="$1" pgp="$W/pgpass"
  has pg_dump || { REASON=no_pg_dump; return 1; }
  printf '%s:%s:%s:%s:%s\n' "$(pgpass_esc "$D_HOST")" "$D_PORT" "$(pgpass_esc "$D_NAME")" "$(pgpass_esc "$D_USER")" "$(pgpass_esc "$D_PASS")" > "$pgp"
  chmod 600 "$pgp"
  if ! PGPASSFILE="$pgp" PGCONNECT_TIMEOUT=15 pg_dump --no-password -h "$D_HOST" -p "$D_PORT" -U "$D_USER" "$D_NAME" > "$dst" 2>"$W/err"; then
    REASON=pg_dump_failed; DETAIL="$(head -n 1 "$W/err" 2>/dev/null)"; return 1
  fi
  dump_footer_ok "$dst" || { REASON=dump_incomplete; return 1; }
  return 0
}

# ---------------------------------------------------------------------------------------------------- uploads
uploads_list() { # appdir listfile: top-level entries of storage/app that are user data (logs/cache/backups are not)
  local base="$1/storage/app" e n
  : > "$2"
  [ -d "$base" ] || return 1
  for e in "$base"/* "$base"/.[!.]*; do
    [ -e "$e" ] || [ -L "$e" ] || continue
    n="${e##*/}"
    case "$n" in backups|cache|framework|logs|tmp|.gitignore|.gitkeep) continue ;; esac
    printf '%s\n' "$n" >> "$2"
  done
  [ -s "$2" ]
}
uploads_have_files() { # appdir listfile
  local base="$1/storage/app" n
  while IFS= read -r n; do
    [ -n "$n" ] || continue
    [ -n "$(find "$base/$n" -type f ! -name .gitignore ! -name .gitkeep 2>/dev/null | head -n 1)" ] && return 0
  done < "$2"
  return 1
}
uploads_kb() { # appdir listfile
  local base="$1/storage/app" n k total=0
  while IFS= read -r n; do
    [ -n "$n" ] || continue
    k="$(du -sk "$base/$n" 2>/dev/null | cut -f1)"; is_int "$k" || k=0
    total=$(( total + k ))
  done < "$2"
  echo "$total"
}

# ---------------------------------------------------------------------------------------------------- one app
B_KEY=""; B_DRV=""; B_DBB=0; B_AB=0; B_OUT=""; B_WARN=""; B_T0=0; DETAIL=""; W=""
fin() { # status [reason]
  local secs line
  secs=$(( ( $(now_ms) - B_T0 ) / 1000 ))
  line="$(date -u +%Y-%m-%dT%H:%M:%SZ) key=$B_KEY driver=${B_DRV:-unknown} db_bytes=$B_DBB archive_bytes=$B_AB seconds=$secs status=$1"
  [ -n "${2:-}" ] && line="$line reason=$2"
  [ -n "$B_WARN" ] && line="$line warn=$B_WARN"
  printf '%s\n' "$line" >> "$MANIFEST"
  printf '%s|%s\n' "$1" "$B_OUT" > "${DOT_BACKUP_RES:-/dev/null}/$B_KEY"
  case "$1" in
    ok) say "$B_KEY: ok ${B_DRV} db=$(hsize "$B_DBB") archive=$(hsize "$B_AB") ${secs}s${B_WARN:+ (warning: $B_WARN)}" ;;
    skipped) say "$B_KEY: skipped ($2)" ;;
    *) say "$B_KEY: FAILED ($2)"; [ -n "$DETAIL" ] && say "  tool said: $(redact "$DETAIL")" ;;
  esac
}

backup_one() { # key appdir   (runs as its own process: _one)
  local key="$1" dir="$2" env="$2/.env" src dbfile="" stamp out up_state=none up_kb=0 db_kb=0 avail rc
  B_KEY="$key"; B_T0="$(now_ms)"; tstart
  W="$ROOT/.stage/$key.$$"; local PAY="$W/payload"
  rm -rf "$W"; mkdir -p "$PAY" || { fin failed stage_dir_unwritable; return 0; }
  chmod 700 "$W" "$PAY"
  trap 'rm -rf "$W"' EXIT
  trap 'rm -rf "$W"; exit 143' TERM INT

  [ -f "$env" ] || { fin skipped no_env_file; return 0; }
  resolve_db "$dir"; B_DRV="$D_DRIVER"

  case "$D_DRIVER" in
    sqlite)
      src="$(sqlite_path "$dir")"
      [ -f "$src" ] || { fin failed sqlite_file_missing; return 0; }
      db_kb=$(( $(file_bytes "$src") / 1024 )) ;;
  esac
  if uploads_list "$dir" "$W/uploads.list" && uploads_have_files "$dir" "$W/uploads.list"; then
    up_kb="$(uploads_kb "$dir" "$W/uploads.list")"
    if [ "$up_kb" -gt $(( UPLOADS_MAX_MB * 1024 )) ]; then up_state=skipped_over_cap; B_WARN="uploads_over_cap"; else up_state=included; fi
  fi
  # free space: the plaintext staging copy plus the archive, with slack. Skipped when df cannot say.
  avail="$(df -Pk "$ROOT" 2>/dev/null | awk 'NR==2{print $4}')"
  if is_int "$avail"; then
    [ "$avail" -ge $(( ( db_kb + up_kb ) * 2 + 20480 )) ] || { fin failed low_disk_space; return 0; }
  fi

  case "$D_DRIVER" in
    sqlite)
      sqlite_snapshot "$src" "$PAY/database.sqlite" || { fin failed "$REASON"; return 0; }
      dbfile=database.sqlite ;;
    mysql)
      mysql_dump "$PAY/database.sql" || { fin failed "$REASON"; return 0; }
      dbfile=database.sql ;;
    pgsql)
      pg_dump_to "$PAY/database.sql" || { fin failed "$REASON"; return 0; }
      dbfile=database.sql ;;
    none) B_WARN="${B_WARN:+$B_WARN,}no_database" ;;
    *) fin failed unsupported_driver; return 0 ;;
  esac
  [ -z "$dbfile" ] || B_DBB="$(file_bytes "$PAY/$dbfile")"
  tstep snapshot "$key"

  if ! cp "$env" "$PAY/env"; then fin failed env_copy_failed; return 0; fi
  chmod 600 "$PAY/env"
  if [ "$up_state" = included ]; then
    tar -C "$dir/storage/app" -cf "$PAY/uploads.tar" -T "$W/uploads.list" >/dev/null 2>"$W/err"; rc=$?
    if [ "$rc" -gt 1 ] || [ ! -s "$PAY/uploads.tar" ]; then fin failed uploads_tar_failed; return 0; fi
    [ "$rc" = 1 ] && B_WARN="${B_WARN:+$B_WARN,}uploads_changed_while_reading"
  fi
  {
    echo "format=1"; echo "key=$key"; echo "driver=$B_DRV"; echo "created=$(date -u +%Y-%m-%dT%H:%M:%SZ)"
    echo "source=${dir#"$HOME"/}"; echo "db_file=${dbfile:-none}"; echo "uploads=$up_state"
  } > "$PAY/meta.txt"
  tstep uploads+meta "$key"

  mkdir -p "$ROOT/$key"; chmod 700 "$ROOT" "$ROOT/$key"
  stamp="$(date -u +%Y%m%d-%H%M%S)"
  out="$ROOT/$key/$key-$stamp.tar.gz.enc"
  while [ -e "$out" ]; do sleep 1; stamp="$(date -u +%Y%m%d-%H%M%S)"; out="$ROOT/$key/$key-$stamp.tar.gz.enc"; done
  tar -C "$PAY" -cf - . 2>"$W/err" | gzip -6 | ossl_enc -out "$out.part" 2>>"$W/err"
  local rcs=("${PIPESTATUS[@]}")
  if [ "${rcs[0]}" != 0 ] || [ "${rcs[1]}" != 0 ] || [ "${rcs[2]}" != 0 ] || [ ! -s "$out.part" ]; then
    rm -f "$out.part"; fin failed pack_or_encrypt_failed; return 0
  fi
  mv "$out.part" "$out" && chmod 600 "$out"
  printf '%s  %s\n' "$(sha256_of "$out")" "${out##*/}" > "$out.sha256"; chmod 600 "$out.sha256"
  B_AB="$(file_bytes "$out")"; B_OUT="$out"
  tstep pack+encrypt+sha256 "$key"
  fin ok
  return 0
}

# ---------------------------------------------------------------------------------------------------- archives and retention
list_archives() { # key -> full paths, oldest first
  local d="$ROOT/$1" f b
  [ -d "$d" ] || return 0
  for f in "$d/$1"-*.tar.gz.enc; do
    [ -f "$f" ] || continue
    b="${f##*/}"
    case "$b" in "$1"-[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]-[0-9][0-9][0-9][0-9][0-9][0-9].tar.gz.enc) printf '%s\n' "$f" ;; esac
  done
}
newest_archive() { list_archives "$1" | tail -n 1; }
archive_stamp() { local b="${1##*/}"; b="${b#"$2"-}"; echo "${b%.tar.gz.enc}"; }
backed_up_keys() { local d; for d in "$ROOT"/*/; do [ -d "$d" ] || continue; d="${d%/}"; echo "${d##*/}"; done; }

# Keep the newest KEEP archives, plus the first archive of each of the last KEEP_MONTHS calendar months (this one included).
prune_key() {
  local key="$1" f st ym mi nowmi seen=" " i=0 total keep kept=0 removed=0 list
  list="$(list_archives "$key")"
  [ -n "$list" ] || return 0
  total="$(printf '%s\n' "$list" | grep -c .)"
  nowmi="${DOT_BACKUP_NOW_YM:-$(date -u +%Y%m)}"; nowmi=$(( 10#${nowmi:0:4} * 12 + 10#${nowmi:4:2} ))
  while IFS= read -r f; do
    [ -n "$f" ] || continue
    i=$(( i + 1 )); keep=0
    st="$(archive_stamp "$f" "$key")"; ym="${st:0:6}"
    [ "$i" -gt $(( total - KEEP )) ] && keep=1
    mi=$(( 10#${ym:0:4} * 12 + 10#${ym:4:2} ))
    case "$seen" in
      *" $ym "*) ;;
      *) seen="$seen$ym "; [ $(( nowmi - mi )) -ge 0 ] && [ $(( nowmi - mi )) -lt "$KEEP_MONTHS" ] && keep=1 ;;
    esac
    if [ "$keep" = 1 ]; then kept=$(( kept + 1 )); else rm -f "$f" "$f.sha256"; removed=$(( removed + 1 )); fi
  done <<< "$list"
  say "prune $key: kept $kept, removed $removed"
}
check_retention_settings() {
  is_int "$KEEP" && [ "$KEEP" -ge 1 ] || { err "DOT_BACKUP_KEEP must be a whole number of at least 1"; return 1; }
  is_int "$KEEP_MONTHS" || { err "DOT_BACKUP_KEEP_MONTHS must be a whole number"; return 1; }
  is_int "$PAR" && [ "$PAR" -ge 1 ] || { err "DOT_BACKUP_PARALLEL must be a whole number of at least 1"; return 1; }
}

take_lock() {
  has flock || { err "flock is not installed on this host"; return 1; }
  mkdir -p "$ROOT" && chmod 700 "$ROOT"
  exec 9>"$ROOT/.lock"
  flock -n 9 || { err "another backup run (or prune) is in progress; exiting without doing anything"; return 75; }
}
trim_log() { # keep the tail of a growing log without replacing the file (an open >> redirect keeps working)
  local f="$1"
  [ -f "$f" ] && [ "$(file_bytes "$f")" -gt 1048576 ] || return 0
  tail -n 2000 "$f" > "$f.t" && cat "$f.t" > "$f"; rm -f "$f.t"
}

# ---------------------------------------------------------------------------------------------------- unpack / inspect
UNPACK_MSG=""
unpack() { # key archive destdir  (destdir exists and is empty; scratch dir for the temporary stderr is $3/..)
  local key="$1" archive="$2" dest="$3" want got rcs
  if [ -f "$archive.sha256" ]; then
    want="$(cut -d' ' -f1 "$archive.sha256")"; got="$(sha256_of "$archive")"
    [ "$want" = "$got" ] || { UNPACK_MSG="sha256 MISMATCH (the archive changed or is damaged)"; return 1; }
    SHA_STATE=ok
  else
    SHA_STATE=missing
  fi
  tstep checksum
  ossl_dec -in "$archive" 2>/dev/null | gzip -dc 2>/dev/null | tar -xf - -C "$dest" 2>/dev/null
  rcs=("${PIPESTATUS[@]}")
  if [ "${rcs[0]}" != 0 ] || [ "${rcs[1]}" != 0 ] || [ "${rcs[2]}" != 0 ]; then
    UNPACK_MSG="decrypt/extract failed (wrong passphrase, or a damaged or truncated archive)"; return 1
  fi
  tstep decrypt+extract
  [ -f "$dest/meta.txt" ] || { UNPACK_MSG="no meta.txt inside the archive"; return 1; }
  [ "$(sed -n 's/^key=//p' "$dest/meta.txt" | head -n 1)" = "$key" ] || { UNPACK_MSG="the archive belongs to another app"; return 1; }
  return 0
}
SHA_STATE=""; INSPECT=""
inspect_payload() { # destdir -> INSPECT summary (counts only); returns 1 on a failed check
  local d="$1" dbf drv up res ec=0
  dbf="$(sed -n 's/^db_file=//p' "$d/meta.txt" | head -n 1)"; drv="$(sed -n 's/^driver=//p' "$d/meta.txt" | head -n 1)"
  up="$(sed -n 's/^uploads=//p' "$d/meta.txt" | head -n 1)"
  INSPECT="driver=$drv"
  case "$dbf" in
    database.sqlite)
      res="$(sqlite_check "$d/database.sqlite")" || ec=1
      INSPECT="$INSPECT db_bytes=$(file_bytes "$d/database.sqlite") $res" ;;
    database.sql)
      INSPECT="$INSPECT db_bytes=$(file_bytes "$d/database.sql")"
      if dump_footer_ok "$d/database.sql"; then INSPECT="$INSPECT dump_footer=ok"
      else INSPECT="$INSPECT dump_footer=MISSING"; ec=1; fi ;;
    *) INSPECT="$INSPECT db=none" ;;
  esac
  if [ -s "$d/env" ]; then INSPECT="$INSPECT env=yes"; else INSPECT="$INSPECT env=NO"; ec=1; fi
  if [ -f "$d/uploads.tar" ]; then INSPECT="$INSPECT uploads_entries=$(tar -tf "$d/uploads.tar" 2>/dev/null | wc -l | tr -d ' ')"; else INSPECT="$INSPECT uploads=$up"; fi
  tstep integrity_check
  return "$ec"
}

# ---------------------------------------------------------------------------------------------------- commands
cmd_init() {
  local t
  mkdir -p "$CONF" "$ROOT" "$ROOT/.stage" "$ROOT/.restore-test"; chmod 700 "$CONF" "$ROOT" "$ROOT/.stage" "$ROOT/.restore-test"
  need_tools run || true
  if [ -e "$PASSFILE" ]; then
    mode_is_600 "$PASSFILE" || { err "the passphrase file exists but its mode is not 600; fix it (chmod 600 $PASSFILE) - not changed by init"; return 1; }
    say "passphrase file already exists ($PASSFILE); left unchanged"
  else
    has openssl || { err "openssl is required"; return 1; }
    ( umask 077; openssl rand -base64 48 > "$PASSFILE" ) || { err "could not create the passphrase file"; return 1; }
    chmod 600 "$PASSFILE"
    say "passphrase file created ($PASSFILE, mode 600, content not shown)."
    say "KEEP A COPY OFF THIS HOST NOW (password manager): without it no backup can be decrypted. How: docs/programme/BACKUPS.md"
  fi
  say "directories ready: $ROOT (700), $CONF (700)"
  for t in openssl tar gzip flock timeout xargs sqlite3 "$PHP" mysqldump pg_dump nice ionice; do
    if has "$t"; then printf '  %-10s present\n' "$t"; else printf '  %-10s MISSING\n' "$t"; fi
  done
  say "needed per driver: sqlite = sqlite3 or php; mysql = mysqldump; pgsql = pg_dump. Anything MISSING there makes the apps that need it fail, loudly."
}

cmd_inventory() {
  local line key dir state rel drv src dbb envp upk newest age_s age flags n_all=0 n_flag=0 n_nobk=0 up_list sched last_off
  say "== dot-backup inventory $(date -u +%Y-%m-%dT%H:%M:%SZ)  (read-only; counts and names only)"
  if pass_ok 2>/dev/null; then say "passphrase file: present, mode 600"; else say "passphrase file: MISSING or mode not 600 (run: dot-backup init)"; fi
  if [ -e "$HOOK" ]; then if [ -x "$HOOK" ]; then say "off-host hook: $HOOK (executable)"; else say "off-host hook: $HOOK present but NOT executable (skipped)"; fi
  else say "off-host hook: none (backups are on this host only)"; fi
  last_off="$(grep ' offsite ' "$MANIFEST" 2>/dev/null | tail -n 1)" || last_off=""
  [ -n "$last_off" ] && say "last off-host hook run: $last_off"
  sched="$(crontab -l 2>/dev/null | grep -F -- '# dot-backup' || true)"
  if [ -n "$sched" ]; then say "schedule: cron line present"
  elif [ -n "$(crontab -l 2>/dev/null)" ]; then say "schedule: NOT scheduled (run the Backups workflow in schedule mode)"
  else say "schedule: unknown (crontab unreadable or answered empty)"; fi
  printf '%-11s %-30s %-7s %-8s %-4s %-8s %-17s %-5s %s\n' KEY FOLDER DRIVER DB .ENV UPLOADS NEWEST-BACKUP AGE FLAGS
  up_list="$(mktemp 2>/dev/null || echo /dev/null)"; NONE_LIST=""
  while IFS=$'\t' read -r key dir state; do
    [ -n "$key" ] || continue
    rel="${dir#"$HOME"/}"
    if [ "$state" = none ]; then NONE_LIST="${NONE_LIST:-} $key"; continue; fi
    n_all=$(( n_all + 1 )); flags=""
    if [ "$state" = ambiguous ]; then printf '%-11s %-30s %-7s %-8s %-4s %-8s %-17s %-5s %s\n' "$key" "$rel" "?" "-" "-" "-" "-" "-" "AMBIGUOUS(>1 artisan; list it in apps.list)"; n_flag=$(( n_flag + 1 )); continue; fi
    resolve_db "$dir"; drv="$D_DRIVER"; dbb="-"; envp=yes
    [ -f "$dir/.env" ] || { envp=NO; flags="$flags NO-ENV"; }
    case "$drv" in
      sqlite) src="$(sqlite_path "$dir")"
              if [ -f "$src" ]; then dbb="$(hsize "$(file_bytes "$src")")"; else dbb=MISSING; flags="$flags DB-MISSING"; fi
              has sqlite3 || has "$PHP" || flags="$flags NO-SQLITE-TOOL" ;;
      mysql) dbb="live"; has mysqldump || flags="$flags NO-MYSQLDUMP" ;;
      pgsql) dbb="live"; has pg_dump || flags="$flags NO-PG_DUMP" ;;
      none) flags="$flags NO-DB" ;;
      noenv) drv="-" ;;
      *) flags="$flags UNSUPPORTED-DRIVER" ;;
    esac
    upk="-"
    if uploads_list "$dir" "$up_list" 2>/dev/null && uploads_have_files "$dir" "$up_list"; then
      upk="$(uploads_kb "$dir" "$up_list")"
      if [ "$upk" -gt $(( UPLOADS_MAX_MB * 1024 )) ]; then flags="$flags UPLOADS-OVER-CAP"; fi
      upk="$(hsize $(( upk * 1024 )))"
    fi
    newest="$(newest_archive "$key")"
    if [ -z "$newest" ]; then newest="none"; age="-"; flags="$flags NO-BACKUP"; n_nobk=$(( n_nobk + 1 ))
    else
      newest="$(archive_stamp "$newest" "$key")"
      age_s=$(( $(date +%s) - $(stamp_epoch "$newest" || echo 0) ))
      age="$(hage "$age_s")"
      [ "$age_s" -gt "$STALE_SECONDS" ] && flags="$flags STALE(>36h)"
    fi
    [ -n "$flags" ] && n_flag=$(( n_flag + 1 ))
    printf '%-11s %-30s %-7s %-8s %-4s %-8s %-17s %-5s %s\n' "$key" "$rel" "$drv" "$dbb" "$envp" "$upk" "$newest" "$age" "${flags# }"
  done <<< "$(discover)"
  [ "$up_list" = /dev/null ] || rm -f "$up_list"
  [ -z "${NONE_LIST:-}" ] || say "docroots without a Laravel app (not backed up):${NONE_LIST}"
  say "== $n_all app(s) found, $n_nobk without any backup, $n_flag flagged"
  [ "$STRICT" = 1 ] && [ "$n_flag" -gt 0 ] && return 1
  return 0
}

cmd_run() {
  local keys="$*" k line key dir state jobs="" n_jobs=0 ok=0 failed=0 skipped=0 t0 archives="" st path RES RUNPFX="" rc
  check_retention_settings || return 1
  need_tools run || return 1
  pass_ok || return 1
  take_lock || return $?
  mkdir -p "$ROOT/.stage" "$ROOT/.restore-test"; chmod 700 "$ROOT/.stage" "$ROOT/.restore-test"
  trim_log "$ROOT/cron.log"; trim_log "$MANIFEST"
  rm -rf "$ROOT/.stage"/* "$ROOT/.restore-test"/verify-* 2>/dev/null   # stale leftovers: we hold the lock, nobody else is running
  RES="$ROOT/.stage/results.$$"; mkdir -p "$RES"; export DOT_BACKUP_RES="$RES"
  trap 'rm -rf "$ROOT/.stage/results.$$"' EXIT
  t0="$(now_ms)"
  RUNPFX="nice -n 19"
  if has ionice && ionice -c3 true 2>/dev/null; then RUNPFX="$RUNPFX ionice -c3"; fi
  if has timeout; then RUNPFX="$RUNPFX timeout -k 20 $APP_TIMEOUT"; else say "warning: timeout is not installed; apps run without a time limit"; fi

  local all; all="$(discover)"
  if [ -n "$keys" ]; then
    for k in $keys; do
      case "$k" in *[!a-z0-9-]*|'') say "$k: FAILED (not a valid app key)"; failed=$(( failed + 1 )); continue ;; esac
      line="$(printf '%s\n' "$all" | awk -F'\t' -v k="$k" '$1==k && $3!="none"')"
      if [ -z "$line" ]; then say "$k: FAILED (no Laravel app with that key on this host)"; failed=$(( failed + 1 )); else jobs="$jobs$line"$'\n'; fi
    done
  else
    jobs="$(printf '%s\n' "$all" | awk -F'\t' '$3!="none"')"$'\n'
  fi
  local joblist=""
  while IFS=$'\t' read -r key dir state; do
    [ -n "$key" ] || continue
    if [ "$state" = ambiguous ]; then
      say "$key: FAILED (more than one artisan under its docroot; list it in $EXTRA)"
      printf '%s key=%s driver=unknown db_bytes=0 archive_bytes=0 seconds=0 status=failed reason=ambiguous_app_folder\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$key" >> "$MANIFEST"
      failed=$(( failed + 1 )); continue
    fi
    joblist="$joblist$key"$'\t'"$dir"$'\n'; n_jobs=$(( n_jobs + 1 ))
  done <<< "$jobs"

  if [ "$n_jobs" -gt 0 ]; then
    # shellcheck disable=SC2086
    printf '%s' "$joblist" | tr '\n\t' '\000\000' | xargs -0 -n 2 -P "$PAR" $RUNPFX bash "$SELF" _one || true
  fi

  while IFS=$'\t' read -r key dir; do
    [ -n "$key" ] || continue
    if [ -f "$RES/$key" ]; then
      st="$(cut -d'|' -f1 "$RES/$key")"; path="$(cut -d'|' -f2- "$RES/$key")"
      case "$st" in
        ok) ok=$(( ok + 1 )); archives="$archives$path"$'\n'; prune_key "$key" ;;
        skipped) skipped=$(( skipped + 1 )) ;;
        *) failed=$(( failed + 1 )) ;;
      esac
    else
      say "$key: FAILED (killed after ${APP_TIMEOUT}s or crashed; no archive kept)"
      printf '%s key=%s driver=unknown db_bytes=0 archive_bytes=0 seconds=%s status=failed reason=timeout_or_crash\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$key" "$APP_TIMEOUT" >> "$MANIFEST"
      rm -rf "$ROOT/.stage/$key".* 2>/dev/null
      failed=$(( failed + 1 ))
    fi
  done <<< "$joblist"
  say "== run finished: $ok ok, $failed failed, $skipped skipped, $(( ( $(now_ms) - t0 ) / 1000 )) s"
  # shellcheck disable=SC2086
  [ "$ok" -gt 0 ] && run_offsite $archives
  rc=0; [ "$failed" -gt 0 ] && rc=1
  return "$rc"
}

# Optional off-host hook: ~/.dot-backup/offsite.sh, called with the new archive paths. Never fatal.
run_offsite() {
  local rc perms tmo
  [ -e "$HOOK" ] || return 0
  [ -x "$HOOK" ] || { say "off-host: $HOOK is not executable; skipped"; return 0; }
  perms="$(ls -ld "$HOOK" | cut -c1-10)"
  case "$perms" in ?????w????|????????w?) say "off-host: $HOOK is writable by group or others; refusing to run it"; return 0 ;; esac
  trim_log "$ROOT/offsite.log"
  tmo=""; has timeout && tmo="timeout -k 20 ${DOT_BACKUP_OFFSITE_TIMEOUT:-1800}"
  # shellcheck disable=SC2086
  DOT_BACKUP_ROOT="$ROOT" DOT_BACKUP_COUNT="$#" $tmo "$HOOK" "$@" >> "$ROOT/offsite.log" 2>&1; rc=$?
  if [ "$rc" = 0 ]; then
    say "off-host: hook ok ($# archive(s) handed over)"
    printf '%s offsite status=ok archives=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$#" >> "$MANIFEST"
  else
    say "off-host: hook FAILED rc=$rc (output in $ROOT/offsite.log); the backups exist on this host only"
    printf '%s offsite status=failed rc=%s archives=%s\n' "$(date -u +%Y-%m-%dT%H:%M:%SZ)" "$rc" "$#" >> "$MANIFEST"
  fi
  return 0
}

cmd_prune() {
  local k keys="$*"
  check_retention_settings || return 1
  take_lock || return $?
  [ -n "$keys" ] || keys="$(backed_up_keys)"
  for k in $keys; do
    case "$k" in *[!a-z0-9-]*|'') err "bad key $k"; continue ;; esac
    prune_key "$k"
  done
}

scratch_dir() { # prefix key -> a new empty directory under ~/dot-backups/.restore-test
  local d="$ROOT/.restore-test/$1-$2-$$"
  mkdir -p "$ROOT/.restore-test" && chmod 700 "$ROOT" "$ROOT/.restore-test" 2>/dev/null
  rm -rf "$d"; mkdir "$d" && chmod 700 "$d" && printf '%s' "$d"
}

cmd_verify() {
  local keys="$*" k a d bad=0 n=0 age_s stamp
  need_tools verify || return 1
  pass_ok || return 1
  [ -n "$keys" ] || keys="$(backed_up_keys)"
  [ -n "$keys" ] || { err "no backups found under $ROOT"; return 1; }
  for k in $keys; do
    case "$k" in *[!a-z0-9-]*|'') err "$k: bad key"; bad=$(( bad + 1 )); continue ;; esac
    a="$(newest_archive "$k")"
    if [ -z "$a" ]; then say "$k: NO BACKUP"; bad=$(( bad + 1 )); continue; fi
    n=$(( n + 1 ))
    d="$(scratch_dir verify "$k")" || { say "$k: FAILED (cannot create scratch)"; bad=$(( bad + 1 )); continue; }
    W="$d"; tstart
    stamp="$(archive_stamp "$a" "$k")"; age_s=$(( $(date +%s) - $(stamp_epoch "$stamp" || echo 0) ))
    if ! unpack_verify "$k" "$a" "$d"; then
      say "$k: FAILED ($UNPACK_MSG) archive=${a##*/}"; bad=$(( bad + 1 ))
    elif inspect_payload "$d/x"; then
      say "$k: verify ok archive=${a##*/} age=$(hage "$age_s") sha256=$SHA_STATE entries=$VERIFY_ENTRIES $INSPECT"
    else
      say "$k: FAILED (content check) archive=${a##*/} $INSPECT"; bad=$(( bad + 1 ))
    fi
    rm -rf "$d"; W=""
  done
  say "== verify: $n archive(s) checked, $bad problem(s)"
  [ "$bad" = 0 ]
}
VERIFY_ENTRIES=0
unpack_verify() { # key archive scratch: tar -t pass, then extract into scratch/x
  local key="$1" a="$2" d="$3" rcs
  VERIFY_ENTRIES="$(ossl_dec -in "$a" 2>/dev/null | gzip -dc 2>/dev/null | tar -tf - 2>/dev/null | wc -l | tr -d ' ')"
  [ "$VERIFY_ENTRIES" -gt 0 ] 2>/dev/null || { UNPACK_MSG="archive does not decrypt/list (wrong passphrase or damaged file)"; [ -f "$a.sha256" ] && [ "$(cut -d' ' -f1 "$a.sha256")" != "$(sha256_of "$a")" ] && UNPACK_MSG="sha256 MISMATCH and the archive does not decrypt"; return 1; }
  mkdir -p "$d/x"
  unpack "$key" "$a" "$d/x"
}

# A restore target may never be, contain or sit inside a live app folder or docroot.
safe_target() { # target
  local t="$1" parent base real g gd
  [ -n "$t" ] || { err "refusing: no target directory given"; return 1; }
  [ ! -L "$t" ] || { err "refusing: the target is a symbolic link"; return 1; }
  parent="$(dirname "$t")"; base="$(basename "$t")"
  [ -d "$parent" ] || { err "refusing: the parent of the target does not exist: $parent"; return 1; }
  real="$(cd "$parent" && pwd -P)/$base"
  if [ -e "$t" ]; then
    [ -d "$t" ] || { err "refusing: the target exists and is not a directory"; return 1; }
    [ -z "$(ls -A "$t")" ] || { err "refusing: the target directory is not empty (a restore never overwrites anything)"; return 1; }
    real="$(cd "$t" && pwd -P)"
  fi
  real="${real%/}"
  [ "$real" != "$(cd "$HOME" && pwd -P)" ] || { err "refusing: the target is the home directory"; return 1; }
  gd="$(printf '%s\n' "$HOME/public_html"; for g in "$HOME"/*."$BASE"; do echo "$g"; done; discover | cut -f2; [ -f "$EXTRA" ] && awk '$1!~/^#/ && NF>=2{print $2}' "$EXTRA")"
  while IFS= read -r g; do
    [ -n "$g" ] && [ -d "$g" ] || continue
    g="$(cd "$g" && pwd -P)"; g="${g%/}"
    case "$real/" in "$g"/*) err "refusing: $real is inside the live folder $g"; return 1 ;; esac
    case "$g/" in "$real"/*) err "refusing: $real contains the live folder $g"; return 1 ;; esac
  done <<< "$gd"
  [ -n "$real" ] && [ "$real" != "/" ] || { err "refusing: target is the filesystem root"; return 1; }
  if [ -d "$ROOT" ]; then
    g="$(cd "$ROOT" && pwd -P)"
    case "$real" in
      "$g/.restore-test/"*) ;;
      "$g"|"$g/"*) err "refusing: targets inside $ROOT must be under $ROOT/.restore-test/ (archives live there)"; return 1 ;;
    esac
  fi
  return 0
}

cmd_restore() {
  local key="${1:-}" archive="${2:-}" target="${3:-}" made=0
  [ $# -eq 3 ] || { err "usage: dot-backup restore <key> <archive> <new-empty-target-dir>"; return 2; }
  case "$key" in *[!a-z0-9-]*|'') err "bad key"; return 2 ;; esac
  need_tools restore || return 1
  pass_ok || return 1
  [ -f "$archive" ] || { err "archive not found: $archive"; return 1; }
  safe_target "$target" || return 1
  if [ ! -d "$target" ]; then mkdir -p "$target" || { err "cannot create the target"; return 1; }; made=1; fi
  chmod 700 "$target"
  tstart
  if ! unpack "$key" "$archive" "$target"; then
    err "restore FAILED: $UNPACK_MSG"
    if [ "$made" = 1 ]; then rm -rf "$target"; else rm -rf "${target:?}"/* "${target:?}"/.[!.]* 2>/dev/null; fi
    return 1
  fi
  local f
  say "restored into $target (sha256=$SHA_STATE):"
  for f in "$target"/*; do [ -e "$f" ] && printf '  %-14s %s bytes\n' "${f##*/}" "$(file_bytes "$f")"; done
  if inspect_payload "$target"; then say "restore check ok: $INSPECT"; else say "restore check FAILED: $INSPECT"; return 1; fi
  say "nothing in any app was changed. To put this data back, follow 'Real restore' in docs/programme/BACKUPS.md."
  return 0
}

cmd_restore_test() {
  local key="${1:-}" k a best=0 sz d t0 secs rc ext
  need_tools restore || return 1
  pass_ok || return 1
  if [ -z "$key" ]; then   # the largest newest archive: the slowest restore, so the measurement is the cautious one
    for k in $(backed_up_keys); do
      a="$(newest_archive "$k")"; [ -n "$a" ] || continue
      sz="$(file_bytes "$a")"; if [ "$sz" -ge "$best" ]; then best="$sz"; key="$k"; fi
    done
  fi
  [ -n "$key" ] || { err "no backups to test"; return 1; }
  case "$key" in *[!a-z0-9-]*) err "bad key"; return 2 ;; esac
  a="$(newest_archive "$key")"; [ -n "$a" ] || { err "$key has no backup"; return 1; }
  mkdir -p "$ROOT/.restore-test"; chmod 700 "$ROOT" "$ROOT/.restore-test"
  d="$ROOT/.restore-test/restore-$key-$$"
  say "restore-test: key=$key archive=${a##*/} archive_bytes=$(file_bytes "$a")"
  TIME=1; t0="$(now_ms)"
  cmd_restore "$key" "$a" "$d"; rc=$?
  secs="$(ms_fmt $(( $(now_ms) - t0 )))"
  ext="$(du -sk "$d" 2>/dev/null | cut -f1)"
  rm -rf "$d"
  say "restore-test result: key=$key restore_seconds=$secs archive_bytes=$(file_bytes "$a") extracted_kb=${ext:-?} status=$([ "$rc" = 0 ] && echo ok || echo FAILED)"
  say "(scratch copy removed; no app, database or docroot was touched)"
  return "$rc"
}

# ---------------------------------------------------------------------------------------------------- crontab (the safe block)
# Same rules as tools/dot-cron-host.sh and the deploy pipelines: one writer (flock on ~/.dot-crontab.lock), a read is
# trusted only when non-empty or the host says "no crontab", a timestamped backup before every write, a read-back
# check, and the previous crontab restored on any mismatch. Only OUR line (marker "# dot-backup") is ever touched.
BMARK="# dot-backup"
read_crontab() { # $1 = file
  local i err_f; err_f="$(mktemp)"
  for i in 1 2 3 4 5; do
    if crontab -l > "$1" 2> "$err_f"; then [ -s "$1" ] && { rm -f "$err_f"; return 0; }
    elif grep -qi "no crontab for" "$err_f"; then : > "$1"; rm -f "$err_f"; return 0; fi
    sleep 2
  done
  err "crontab -l answered empty or failed five times"; rm -f "$err_f"; return 1
}
cron_normalize() { grep -v '^[[:space:]]*$' "$1" | grep -v '^# \(DO NOT EDIT\|(.* installed on\|(Cron version\)' | sed -E 's/[[:space:]]+$//' | sort; }
cron_write() { # new old
  local new="$1" old="$2" chk nn nc; chk="$(mktemp)"
  cp "$old" "$HOME/.dot-crontab.backup-$(date -u +%Y%m%dT%H%M%S)"
  crontab "$new"
  read_crontab "$chk" || { crontab "$old"; rm -f "$chk"; err "read-back failed; previous crontab restored"; return 1; }
  nn="$(mktemp)"; nc="$(mktemp)"; cron_normalize "$new" > "$nn"; cron_normalize "$chk" > "$nc"
  if ! cmp -s "$nn" "$nc"; then
    err "read-back differs from what was written; previous crontab restored"
    rm -f "$nn" "$nc" "$chk"; crontab "$old"; return 1
  fi
  rm -f "$nn" "$nc" "$chk"
}
cron_lock() {
  has flock || { err "flock is not installed on the host"; return 1; }
  exec 8>"$HOME/.dot-crontab.lock"; flock -w 120 8 || { err "could not take the crontab lock within 120 s"; return 1; }
}
cmd_schedule() { # schedule | unschedule
  local mode="$1" old new fl pathpart="" line
  has crontab || { err "crontab is not available"; return 1; }
  need_tools schedule || return 1
  cron_lock || return 1
  old="$(mktemp)"; new="$(mktemp)"
  read_crontab "$old" || { rm -f "$old" "$new"; return 1; }
  if [ "$mode" = schedule ]; then
    [ -x "$BIN" ] || { err "$BIN is not installed (run the Backups workflow in install mode first)"; rm -f "$old" "$new"; return 1; }
    fl="$(command -v flock)"
    # cron's PATH is minimal: carry the PATH this session found php/mysqldump/pg_dump on (paths only, no secrets)
    case "$PATH" in *[!A-Za-z0-9_./:+-]*) ;; *) pathpart="env PATH=$PATH " ;; esac
    line="40 2 * * * ${pathpart}$fl -n $ROOT/.cron.lock $BIN run >> $ROOT/cron.log 2>&1 $BMARK"
    { grep -vF -- "$BMARK" "$old" || true; echo "$line"; } > "$new"
  else
    { grep -vF -- "$BMARK" "$old" || true; } > "$new"
  fi
  local no nn; no="$(mktemp)"; nn="$(mktemp)"; cron_normalize "$old" > "$no"; cron_normalize "$new" > "$nn"
  if cmp -s "$no" "$nn"; then say "crontab already in the wanted state; nothing written"; rm -f "$old" "$new" "$no" "$nn"; return 0; fi
  rm -f "$no" "$nn"
  cron_write "$new" "$old" || { rm -f "$old" "$new"; return 1; }
  if [ "$mode" = schedule ]; then say "cron line installed: nightly 02:40 server time, flock -n, log $ROOT/cron.log; $(grep -vcF -- "$BMARK" "$old" || true) other line(s) untouched"
  else say "cron line removed; $(grep -vcF -- "$BMARK" "$old" || true) other line(s) untouched"; fi
  rm -f "$old" "$new"
}

# ---------------------------------------------------------------------------------------------------- dispatch
main() {
  local n=$# i=0 a CMD=""
  while [ "$i" -lt "$n" ]; do
    a="$1"; shift
    case "$a" in
      --time) TIME=1 ;;
      --strict) STRICT=1 ;;
      -h|--help|help) usage; return 0 ;;
      *) set -- "$@" "$a" ;;
    esac
    i=$(( i + 1 ))
  done
  export DOT_BACKUP_TIME="$TIME"
  [ $# -gt 0 ] || { usage; return 2; }
  CMD="$1"; shift
  case "$CMD" in
    init) cmd_init ;;
    inventory) cmd_inventory ;;
    run) cmd_run "$@" ;;
    verify) cmd_verify "$@" ;;
    restore) cmd_restore "$@" ;;
    restore-test) cmd_restore_test "$@" ;;
    prune) cmd_prune "$@" ;;
    schedule) cmd_schedule schedule ;;
    unschedule) cmd_schedule unschedule ;;
    _one) [ $# -eq 2 ] || return 2; backup_one "$1" "$2" ;;
    *) err "unknown command $CMD"; usage >&2; return 2 ;;
  esac
}
main "$@"
