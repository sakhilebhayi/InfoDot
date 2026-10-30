# InfoDot baseline (Phase 0), 2026-10-30

Measured on `main` at `c89286f` (PHP 8.5.9 locally, 8.4 in CI; Laravel 12.70.2, Jetstream 5.5, Livewire 3.8.11, Sanctum 4.3.2). Every number below was produced by a command or a CI job, not estimated. Nothing was run against production except read-only GET requests to infodot.co.za.

## What the application is

258 PHP files in `app/`, 112 test files, 368 lines of routes across `web.php` (240), `api.php` (46), `jetstream.php` (41) and `channels.php`. Companies are Jetstream teams with roles owner, admin, manager, member. Seven server-to-server endpoints (SSO redeem and token, resource registry, knowledge candidates, Billing ticket events, LiveKit webhook) are signed or secret-guarded. `QUEUE_CONNECTION=sync`; no class implements `ShouldQueue`; background work is 15 scheduled commands run by one cron runner (115-second limit per pass, four apps at a time). Production runs on shared cPanel hosting (1 GB RAM, 30 entry processes for the whole account), MySQL.

## Measurements

| Check | Command or source | Baseline result | After PR #161 |
|---|---|---|---|
| Composer advisories | `composer audit` | 1 medium (symfony/mime, CVE-2026-45070) | 0 |
| npm advisories | `npm audit` | 2 moderate (`@tailwindcss/typography` → `postcss-selector-parser`, build-time CPU exhaustion on crafted CSS) | 0 (`overrides` pins the parser; `npm run build` verified) |
| Static analysis | `phpstan analyse` level 5 (Larastan) | 173 errors in 41 files; not in CI | 0 new; the 173 are in `phpstan-baseline.neon` and may only shrink; CI gate |
| Code style | `pint --test` | 39 files fail; not in CI | 0 fail; CI gate |
| Tests | `php artisan test` | 1,181 passed, 4 skipped | 1,215 passed, 4 skipped (34 new) |
| Line coverage of `app/` | CI job with pcov (no coverage driver locally) | not measured | **94.0%** (5,843 of 6,216 lines); methods 82.1%, classes 58.8% |
| CI gate before deploy | `.github/workflows/ci.yml` | tests only, although `deploy.yml` described Pint and a build check | advisories (high/critical block), PHPStan, Pint, OpenAPI parse, tests; separate frontend build + `npm audit --audit-level=high`; coverage job |
| Branch protection | GitHub API | none; no rulesets | unchanged (Owner decision, P-01) |
| Dependabot security updates | GitHub API | disabled | unchanged (P-02) |
| Secret scanning and push protection | GitHub API | enabled | unchanged |
| Security headers, live | `curl -I https://infodot.co.za/` | none of HSTS, X-Frame-Options, nosniff, Referrer-Policy, Permissions-Policy | added in PR #161 (live after deploy) |
| Cookies, live | same | session and CSRF cookies `Secure`, session `HttpOnly`, `SameSite=Lax` | unchanged (good) |
| Sensitive paths, live | GET `/.env`, `/.git/config`, `/storage/logs/laravel.log` | 403 | unchanged (good) |
| Debug and tooling endpoints, live | GET `/phpinfo.php`, `/telescope`, `/horizon`, `/docs`, `/composer.json` | 404 | unchanged (good) |
| Health | GET `/up` | 200 `OK` (runs `select 1`) | adds `/ready` (database, cache, storage; "ok" or "degraded" only) |
| Outdated direct dependencies | `composer outdated --direct` | Laravel 13, Livewire 4, Scout 11, PHPUnit 13 and Guzzle 8 majors available; minors for Sanctum, Reverb, Sentry, Cashier | not changed; majors need their own plan |

## What could not be measured, and why

- **Response times and throughput.** No load test has been run. This needs a seeded copy of the database in a staging environment, which does not exist. Nothing in this report supports a claim about how many users InfoDot can serve.
- **Real run times of the scheduled commands.** The reliability review identified the ones most likely to exceed the runner's 115 seconds; they were not timed on production.
- **Queue behaviour.** There is no queue worker, so there is nothing to measure; async work is `afterResponse` closures and scheduled commands.
- **Backup restore.** Never performed for InfoDot's MySQL dump (C-10). The BACKUPS document records this honestly; it also contradicts itself on whether the first run happened.
- **Production configuration.** `.env` on the host was not read. Questions whose answer lives there (Sentry DSN, the real `APP_KEY`, `TRUSTED_PROXIES`, the `sessions.payload` column type, MySQL version) are marked Owner in the register.
- **Existing audits.** `docs/enterprise-platform-audit.md` (2026-10-17), `docs/programme/SECURITY_ASSESSMENT.md`, `PLATFORM_SCORECARDS.md` and `APP_STATUS_INFODOT.md` were read for context but not trusted; nothing here was copied from them.

## Reading the numbers

Line coverage is high, so the weakness is not the volume of tests. It is what they do not cover: concurrency (SQLite in tests ignores row locks), the production-only parts (cron, MySQL, hosting), and several tenant boundaries that had no test that fails without the check. The new regression tests each fail on the previous code (verified for the money, tenant and session fixes).
