# InfoDot Deployment Guide

## Purpose

This guide covers the steps to deploy InfoDot to production, manage environment configuration, run long-lived processes, and perform zero-downtime releases. It also documents how to roll back safely.

---

## Target Production Stack

| Component | Service |
|---|---|
| Web process | PHP-FPM + Nginx or Laravel Octane |
| Database | PostgreSQL 16 |
| Cache / Queue driver | Redis |
| WebSockets | Laravel Reverb (port 8080) |
| Queue worker | `php artisan queue:work` supervised by Supervisor |
| Search | Meilisearch |
| File storage | AWS S3 via Flysystem |
| Error monitoring | Sentry |
| Asset serving | CDN-backed S3 or Nginx static file serving |

---

## Environment Variables

All environment-specific configuration must go in `.env`. Never commit `.env` to source control. Use a secrets manager (AWS Secrets Manager, Doppler, or equivalent) to inject `.env` values into the production environment.

### Critical production values

```env
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...            # 32-char random key — rotate periodically
APP_URL=https://infodot.app

DB_CONNECTION=pgsql
DB_HOST=your-postgres-host
DB_PORT=5432
DB_DATABASE=infodot
DB_USERNAME=infodot_app       # dedicated, least-privilege DB user
DB_PASSWORD=strong-password

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=your-redis-host
REDIS_PORT=6379

BROADCAST_CONNECTION=reverb
REVERB_APP_ID=...
REVERB_APP_KEY=...
REVERB_APP_SECRET=...
REVERB_HOST=ws.infodot.app
REVERB_PORT=8080
REVERB_SCHEME=https

FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=...
AWS_SECRET_ACCESS_KEY=...
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=infodot-production

SCOUT_DRIVER=meilisearch
MEILISEARCH_HOST=https://search.infodot.app
MEILISEARCH_KEY=...

SENTRY_LARAVEL_DSN=https://...@sentry.io/...

MAIL_MAILER=ses
# or smtp — configure accordingly

SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SANCTUM_STATEFUL_DOMAINS=infodot.app
```

---

## Pre-Deploy Checklist

Before running a deployment, verify:

- [ ] All tests pass: `php artisan test`
- [ ] PHPStan passes: `./vendor/bin/phpstan analyse --level=5`
- [ ] `npm run build` produces a clean Vite build
- [ ] `composer audit` reports no critical vulnerabilities
- [ ] Database migrations are backwards compatible with the running code (see zero-downtime notes)
- [ ] `.env` on the target environment has all required keys for the new release
- [ ] Sentry release is created or will be created during deploy

---

## Deployment Steps

### 1. Pull latest code

```bash
git pull origin main
```

### 2. Install / update Composer dependencies

```bash
composer install --no-dev --optimize-autoloader
```

### 3. Build front-end assets

```bash
npm ci
npm run build
```

### 4. Run database migrations

```bash
php artisan migrate --force
```

The `--force` flag is required to run migrations in production without an interactive prompt. Never pass `--seed` in production unless explicitly required and reviewed.

### 5. Clear and rebuild framework caches

Run these in order. Skipping or reordering can cause stale config or route caches.

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### 6. Restart queue workers

After deployment, queue workers must be restarted to pick up code changes. If using Supervisor:

```bash
php artisan queue:restart
```

Supervisor will restart workers automatically after this signal. Verify they come back up with:

```bash
supervisorctl status
```

### 7. Restart Reverb

```bash
supervisorctl restart reverb
```

Or if managing the process manually:

```bash
php artisan reverb:start --port=8080
```

### 8. Re-index Scout (if schema changed)

If new models were added to Scout or `toSearchableArray()` changed:

```bash
php artisan scout:import "App\Models\Solution"
php artisan scout:import "App\Models\Question"
# repeat for each affected model
```

---

## Zero-Downtime Deployment Rules

Follow these rules to avoid downtime during migrations and deploys.

### Backwards-compatible migrations only

Migrations that run while the old code is still serving requests must be backwards compatible. The old code must continue to work on the new schema until the deploy finishes.

Safe:
- adding a nullable column
- adding an index
- adding a new table

Unsafe without a multi-step migration:
- renaming a column or table
- adding a non-nullable column without a default
- dropping a column that the old code reads

For breaking schema changes, use a three-step approach:
1. Add the new column or table (deploy step 1)
2. Migrate data with a job or command
3. Remove the old column (deploy step 2, after confirming new code is stable)

### Use a maintenance window for destructive operations

For destructive operations that cannot be made safe: schedule a maintenance window, notify connected platforms, bring the app down cleanly with `php artisan down`, and restore with `php artisan up` after the migration.

---

## Supervisor Configuration

Supervisor manages the queue worker and Reverb process. Example configuration:

```ini
[program:infodot-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/infodot/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/infodot-worker.log
stopwaitsecs=3600

[program:reverb]
process_name=%(program_name)s
command=php /var/www/infodot/artisan reverb:start --port=8080
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/supervisor/reverb.log
```

---

## Rollback Procedure

If a deployment causes an error rate spike or critical failure:

### Application rollback

```bash
# Revert to the previous Git commit
git checkout <previous-commit-or-tag>
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
supervisorctl restart reverb
```

### Database rollback

If a migration needs to be rolled back:

```bash
php artisan migrate:rollback
```

Only roll back one batch at a time. Confirm which migrations are in the last batch first:

```bash
php artisan migrate:status
```

Never roll back migrations that other platforms depend on (shared tables) without coordinating with all platform teams.

---

## Health Checks

Set up the following health check endpoints for load balancer probes and uptime monitoring:

```php
// routes/web.php
Route::get('/health', function () {
    return response()->json(['status' => 'ok']);
})->name('health');
```

For a more thorough check during deploy:

```bash
php artisan about
```

This outputs environment, driver, and configuration status.

---

## Post-Deploy Verification

After each deployment, verify:

- [ ] The home and dashboard pages load without errors
- [ ] Sentry receives a test event or shows no new errors
- [ ] Queue workers are processing jobs (check Horizon or `queue:monitor`)
- [ ] Reverb is accepting WebSocket connections
- [ ] Search returns results for a known query
- [ ] Ecosystem token issuance and handoff to at least one satellite app works
