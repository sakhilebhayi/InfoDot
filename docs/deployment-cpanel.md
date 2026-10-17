# Deploying InfoDot

Production is `https://infodot.co.za`, on the shared cPanel host that also runs the other Dot apps. The docroot is `~/public_html` and the app lives in `~/public_html/main`. InfoDot is the SSO hub; the platform switcher hands signed-in users to the other platforms.

| Workflow | Runs | What it does |
|---|---|---|
| `ci.yml` | every PR and push; called by `deploy.yml` | PHPUnit. The release gate. |
| `drift.yml` | manual, **read-only** | Compares every tracked file of `main` with the host's copy (sha256) and prints counts and paths. Run it before the takeover. |
| `deploy.yml` | manual, `confirm=adopt` once, then `confirm=deploy` | See below. |
| `server-logs.yml` | manual, read-only | Tails the newest Laravel log. |

## Why there is a takeover step

Nothing in the repository says how the host copy got there. On 2026-10-17 its `/login`, `/register` and `/forgot-password` answered 500 with **"Vite manifest not found"** (`public/build/manifest.json` missing: the frontend was never built on the host), and the site was serving full debug error pages. The host has no Node, so the frontend is built on the runner and synced over. **Run the drift report first.**

## First time (once)

1. Repository secrets: `DEPLOY_HOST` (`infodot.co.za`), `DEPLOY_USER` (`infodotc`), `DEPLOY_SSH_KEY`, `DEPLOY_KNOWN_HOSTS`. `DEPLOY_PATH` is optional; set it to `/home/infodotc/public_html/main` to skip discovery. Without it the app is the one `artisan` found under `~/public_html` (or `~/infodot.co.za` if that exists).
2. Run **Drift Report**. Read it.
3. Run **Deploy to Production** with `confirm=adopt` once. It makes a rollback copy, turns the folder into a checkout of `main` without deleting anything untracked (`.env`, `storage/`), installs dependencies, ships the build, forces `APP_DEBUG=false`, lists pending migrations (it does **not** run them unless you also pass `migrate=migrate`), rebuilds the caches and checks the site.
4. InfoDot's database is PostgreSQL in production (to be confirmed from the host `.env`), which the rollback tarball does **not** cover. Take a database backup in cPanel, check the pending-migration list, then run `confirm=deploy`.

## Every release after that

Run **Deploy to Production** with `confirm=deploy`. It runs the test gate first.

## Safety nets

- A rollback copy of the app folder (without `vendor/`, `node_modules/`, logs and the build) is written to `~/dot-infodot-<mode>-<time>.tgz`; the newest three are kept. To restore: stop writes, `tar xzf` it over the app folder, `php artisan up`.
- A failure after maintenance mode starts **leaves the site down** and says so. A failure before it says the site is untouched.
- `APP_DEBUG` is forced to `false` on every run. `APP_ENV=local` and an `http://` `APP_URL` are reported as warnings.
- Git hooks on the host never run during a release.
- No cron lines: InfoDot has no scheduled commands and no queued jobs.
- The smoke check requests `/`, `/login`, `/up`, the built CSS and a harmless `POST /up`, and fails the run if any is wrong or a debug page is served. `/up` is a GET-only route added for this.
- Platform URLs come from `config/ecosystem.php` and can be overridden with `DOT_*_URL`; the SSO handoff only sends tokens to `ECOSYSTEM_TRUSTED_DOMAINS` (see PR #3).

## Not covered yet

No Dot.Brain guardian health document, no backups, no scheduler probe.
