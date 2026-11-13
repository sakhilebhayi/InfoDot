#!/usr/bin/env bash
# Read-only diagnostics bundle for the InfoDot production app: what failed, and the host facts needed to explain it.
#
# It runs ON the host (piped over SSH by .github/workflows/server-logs.yml) and writes nothing: no file, no mode, no
# link, no database row. Everything goes to stdout. The output contains application error messages, which can hold
# personal information, and this repository is PUBLIC, so the workflow never prints it: it encrypts the bundle to the
# key in tools/diagnostics-public-key.pem and uploads only the ciphertext. Do not `tee` this script into a job log.
#
# Not included on purpose: .env values other than a short allow-list of non-secret settings, request bodies, session
# data, and any row of personal data (the database section is counts only).
#
#   DEPLOY_PATH=/home/<user>/public_html/main bash diagnostics-host.sh
set -uo pipefail

cd "${DEPLOY_PATH:?DEPLOY_PATH is required}" || exit 1
DOCROOT="$(dirname "$DEPLOY_PATH")"
PHP_BIN="$(command -v php || true)"
[ -x /opt/alt/php84/usr/bin/php ] && PHP_BIN=/opt/alt/php84/usr/bin/php
DAYS=14

sec() { printf '\n===== %s =====\n' "$1"; }

sec "clock and release"
date -u '+%Y-%m-%d %H:%M:%S UTC'
git rev-parse --short HEAD 2>&1
git status --short 2>&1 | head -20

sec "php (command line; the web handler may differ, see the ini files below)"
"$PHP_BIN" -v 2>&1 | head -1
"$PHP_BIN" -r '
foreach (["memory_limit","upload_max_filesize","post_max_size","max_file_uploads","file_uploads","max_execution_time","max_input_time","max_input_vars","upload_tmp_dir","sys_temp_dir","open_basedir","disable_functions","log_errors","display_errors","error_log","session.save_path"] as $k) { printf("%s=%s\n", $k, ini_get($k)); }
$t = sys_get_temp_dir(); printf("temp_dir=%s writable=%s\n", $t, is_writable($t) ? "yes" : "NO");
printf("loaded_ini=%s\n", php_ini_loaded_file() ?: "none");
printf("extensions=%s\n", implode(",", get_loaded_extensions()));
if (function_exists("gd_info")) { $g = gd_info(); printf("gd=%s jpeg=%s png=%s webp=%s\n", $g["GD Version"] ?? "?", ($g["JPEG Support"] ?? false) ? "y" : "n", ($g["PNG Support"] ?? false) ? "y" : "n", ($g["WebP Support"] ?? false) ? "y" : "n"); } else { echo "gd=MISSING\n"; }
' 2>&1

sec "php settings files the web server reads"
for f in "$DOCROOT/.user.ini" "$DOCROOT/php.ini" "$DEPLOY_PATH/.user.ini" "$DEPLOY_PATH/php.ini" "$DEPLOY_PATH/public/.user.ini" "$DEPLOY_PATH/public/php.ini"; do
  if [ -f "$f" ]; then echo "--- ${f#"$HOME"/}"; grep -v -E '^\s*(;|$)' "$f" | head -60; fi
done
echo "--- docroot .htaccess (handler, php and limit lines only)"
grep -n -i -E 'php|lsapi|AddHandler|SetHandler|LimitRequestBody|SecRule|SecFilter|mod_security' "$DOCROOT/.htaccess" 2>/dev/null | cut -c1-200 | head -40

sec "disk"
df -h "$HOME" 2>&1 | tail -2
df -i "$HOME" 2>&1 | tail -1
quota -s 2>/dev/null | tail -4
du -sh storage/app storage/logs storage/framework 2>&1

sec "storage paths, owners and modes"
ls -ld storage storage/app storage/app/public storage/app/public/profile-photos storage/app/livewire-tmp storage/app/attachments \
       storage/framework storage/framework/sessions storage/framework/cache storage/framework/views storage/logs bootstrap/cache 2>&1 | sed "s#$HOME#~#g"
echo "--- public storage link"
for l in "$DOCROOT/storage" "$DEPLOY_PATH/public/storage"; do
  if [ -L "$l" ]; then echo "${l#"$HOME"/} -> $(readlink "$l" | sed "s#$HOME#~#g") (target exists: $([ -e "$l" ] && echo yes || echo NO))"
  elif [ -d "$l" ]; then echo "${l#"$HOME"/} is a REAL DIRECTORY with $(find "$l" -type f 2>/dev/null | wc -l) files"
  else echo "${l#"$HOME"/} does not exist"; fi
done
echo "profile photo files stored: $(find storage/app/public/profile-photos -type f 2>/dev/null | wc -l)"
echo "attachment files stored: $(find storage/app/attachments -type f 2>/dev/null | wc -l)"
echo "livewire temp files: $(find storage/app/livewire-tmp -type f 2>/dev/null | wc -l)"
echo "whoami=$(id -un) umask=$(umask)"

sec "settings (allow-listed, non-secret)"
grep -E '^(APP_ENV|APP_DEBUG|APP_URL|LOG_CHANNEL|LOG_LEVEL|LOG_DAILY_DAYS|SESSION_DRIVER|SESSION_DOMAIN|SESSION_SECURE_COOKIE|SESSION_SAME_SITE|SESSION_LIFETIME|CACHE_DRIVER|CACHE_STORE|QUEUE_CONNECTION|MAIL_MAILER|MAIL_HOST|MAIL_PORT|MAIL_ENCRYPTION|MAIL_SCHEME|MAIL_FROM_ADDRESS|FILESYSTEM_DRIVER|FILESYSTEM_DISK|SCOUT_DRIVER|BROADCAST_DRIVER|BROADCAST_CONNECTION|DB_CONNECTION|TRUSTED_PROXIES|LIVEWIRE_ASSET_URL|UX_SIGNALS|SECURITY_CSP_REPORT_ONLY|BCRYPT_ROUNDS)=' .env 2>&1
echo "--- names of every setting present (no values)"
sed -n 's/^\([A-Za-z0-9_]*\)=.*/\1/p' .env 2>/dev/null | tr '\n' ' '; echo
echo "sentry dsn set: $(grep -c -E '^SENTRY_LARAVEL_DSN=.+' .env 2>/dev/null)"

sec "application state"
"$PHP_BIN" artisan about --only=environment,cache,drivers 2>&1 | head -40
echo "--- pending migrations: $("$PHP_BIN" artisan migrate:status 2>/dev/null | grep -c -i pending)"
echo "--- ops:check"
"$PHP_BIN" artisan ops:check 2>&1 | tail -25

sec "log files"
ls -la --time-style=long-iso storage/logs 2>&1 | tail -30

LOGS="$(ls -1t storage/logs/laravel*.log 2>/dev/null | head -"$DAYS")"
LEVELS='^\[[0-9-]+ [0-9:]+\] [A-Za-z]+\.(ERROR|CRITICAL|ALERT|EMERGENCY)'

sec "errors per log file (newest first)"
for f in $LOGS; do printf '%6s  %s\n' "$(grep -c -E "$LEVELS" "$f")" "$f"; done

sec "exceptions by class, last $DAYS files"
# shellcheck disable=SC2086
[ -z "$LOGS" ] || cat $LOGS | grep -oE '\[object\] \([A-Za-z0-9_\\]+' | sort | uniq -c | sort -rn | head -40

sec "error lines, last 200 (message and first exception line, cut at 1500 characters)"
# shellcheck disable=SC2086
[ -z "$LOGS" ] || for f in $(echo $LOGS | tr ' ' '\n' | tac); do grep -E "$LEVELS" "$f" | cut -c1-1500; done | tail -200

sec "stack tops for the last 30 errors"
# shellcheck disable=SC2086
[ -z "$LOGS" ] || for f in $(echo $LOGS | tr ' ' '\n' | tac); do
  awk -v re="$LEVELS" '$0 ~ re { n = 14 } n > 0 { print substr($0, 1, 400); n--; if (n == 0) print "--" }' "$f"
done | tail -480

sec "php and web server error logs"
for f in "$DOCROOT/error_log" "$DEPLOY_PATH/error_log" "$DEPLOY_PATH/public/error_log" "$HOME/logs/error_log"; do
  if [ -f "$f" ]; then echo "--- ${f#"$HOME"/} ($(wc -c < "$f") bytes, modified $(date -u -r "$f" '+%Y-%m-%d %H:%M' 2>/dev/null))"; tail -120 "$f" | cut -c1-600; fi
done

sec "database (counts only)"
read -r -d '' PHP_COUNTS <<'PHP' || true
$q = [
  'users' => fn () => DB::table('users')->count(),
  'users_unverified' => fn () => DB::table('users')->whereNull('email_verified_at')->count(),
  'users_by_day_14d' => fn () => DB::table('users')->where('created_at', '>=', now()->subDays(14))->selectRaw('date(created_at) d, count(*) c, sum(case when email_verified_at is null then 1 else 0 end) unverified')->groupBy('d')->orderBy('d')->get(),
  'users_with_photo' => fn () => DB::table('users')->whereNotNull('profile_photo_path')->count(),
  'users_without_personal_team' => fn () => DB::table('users')->whereNotExists(fn ($s) => $s->from('teams')->whereColumn('teams.user_id', 'users.id')->where('personal_team', 1))->count(),
  'users_without_current_team' => fn () => DB::table('users')->whereNull('current_team_id')->count(),
  'users_without_uuid' => fn () => DB::table('users')->whereNull('uuid')->count(),
  'duplicate_emails' => fn () => DB::table('users')->selectRaw('lower(email) e')->groupBy('e')->havingRaw('count(*) > 1')->get()->count(),
  'businesses' => fn () => DB::table('teams')->where('personal_team', 0)->count(),
  'businesses_by_day_30d' => fn () => DB::table('teams')->where('personal_team', 0)->where('created_at', '>=', now()->subDays(30))->selectRaw('date(created_at) d, count(*) c')->groupBy('d')->orderBy('d')->get(),
  'businesses_without_profile_row' => fn () => DB::table('teams')->where('personal_team', 0)->whereNotExists(fn ($s) => $s->from('team_profiles')->whereColumn('team_profiles.team_id', 'teams.id'))->count(),
  'businesses_whose_owner_is_missing' => fn () => DB::table('teams')->whereNotExists(fn ($s) => $s->from('users')->whereColumn('users.id', 'teams.user_id'))->count(),
  'profiles_by_visibility' => fn () => DB::table('team_profiles')->join('teams', 'teams.id', '=', 'team_profiles.team_id')->where('teams.personal_team', 0)->selectRaw("visibility, count(*) c, sum(case when description is null or description = '' then 1 else 0 end) without_description, sum(case when restricted_at is not null then 1 else 0 end) restricted")->groupBy('visibility')->get(),
  'businesses_a_visitor_can_see' => fn () => DB::table('team_profiles')->join('teams', 'teams.id', '=', 'team_profiles.team_id')->where('teams.personal_team', 0)->where('visibility', 'public')->whereNotNull('description')->where('description', '<>', '')->whereNull('restricted_at')->count(),
  'verifications_by_status' => fn () => DB::table('business_verifications')->selectRaw('status, count(*) c')->groupBy('status')->get(),
  'attachments_by_kind_and_role' => fn () => DB::table('attachments')->selectRaw('kind, role, count(*) c, max(created_at) latest')->groupBy('kind', 'role')->get(),
  'attachments_by_day_14d' => fn () => DB::table('attachments')->where('created_at', '>=', now()->subDays(14))->selectRaw('date(created_at) d, count(*) c')->groupBy('d')->orderBy('d')->get(),
  'failed_jobs' => fn () => DB::table('failed_jobs')->count(),
  'failed_jobs_latest' => fn () => DB::table('failed_jobs')->orderByDesc('id')->limit(5)->get(['failed_at', 'queue'])->map(fn ($r) => (array) $r),
  'failed_job_reasons' => fn () => DB::table('failed_jobs')->orderByDesc('id')->limit(5)->pluck('exception')->map(fn ($e) => substr(strtok((string) $e, "\n"), 0, 300)),
  'queued_jobs_waiting' => fn () => DB::table('jobs')->count(),
  'sessions' => fn () => DB::table('sessions')->count(),
  'sso_platforms' => fn () => DB::table('dot_platforms')->selectRaw('status, count(*) c')->groupBy('status')->get(),
];
$o = [];
foreach ($q as $k => $f) { try { $o[$k] = $f(); } catch (\Throwable $e) { $o[$k] = 'ERROR: '.substr($e->getMessage(), 0, 160); } }
echo json_encode($o, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), "\n";
PHP
"$PHP_BIN" artisan tinker --execute="$PHP_COUNTS" 2>&1 | tail -220

sec "end"
