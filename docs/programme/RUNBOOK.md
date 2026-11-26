# InfoDot runbook index (R-72, O-14)

One page that says where each operational procedure lives. Every step below is done from GitHub Actions or the
owner's own machine; no secret value appears in this repository.

## Release (deploy)

Order, every time. Never let two deploys overlap (the workflow's concurrency group also refuses it).

1. Back up and check the backup:
   `gh workflow run backups.yml -f mode=run -f keys=infodot`, then `gh workflow run backups.yml -f mode=verify -f keys=infodot`.
2. Release: `gh workflow run deploy.yml -f confirm=deploy` (from main).
3. Confirm the run's head commit is main's (`gh run view <id> --json headSha`).
4. Read the run: it backs the database up again itself (`dot-backup run` + `verify`), starts maintenance mode (or stops if it
   cannot), resets the host to the commit CI tested, migrates, caches, comes back up, installs the cron lines, then smoke-checks
   `/`, `/login`, `/up`, `/ready`, the built assets and the app-folder guard.
5. Check the live pages the change touched, in a browser.

What the workflow forces on the host every time: `APP_DEBUG=false`, `APP_ENV=production`, `QUEUE_CONNECTION=database`,
`CACHE_DRIVER=database`, `LOG_LEVEL=info` when it was missing or `debug`. Source: `.github/workflows/deploy.yml` (header).

## When a release goes wrong

| What you see | What it means | What to do |
|---|---|---|
| Red before "maintenance mode" | nothing changed; the old release still serves | read the error, fix, run again |
| Red after maintenance mode started | the site is **down on purpose** (no guessed rollback) | `server-logs.yml` for the cause; then either run the deploy again with a fix, or roll back (below) |
| Smoke check red | the new release is **live** but unhealthy | `server-logs.yml`; deploy a fix, or roll back |

**Roll back:** the run names two things: the rollback copy of the code folder (`~/dot-infodot-deploy-<stamp>.tgz`, newest three
kept) and the database archive from `dot-backup run infodot`. Code: `php artisan down`, extract the copy over the app folder,
`php artisan up`. Database: restore the archive as in `BACKUPS.md` section "Restore, step by step", into a scratch database first.
The full database restore has not been drilled yet: `runbooks/mysql-restore-drill.md` (owner item O-01).

## Backups

`BACKUPS.md`: what is backed up, encryption and the passphrase, the nightly schedule (02:40), retention, the Mac pull (04:15),
restore steps, RPO/RTO. Workflow: `backups.yml` (`install`, `run`, `verify`, `restore-test`, `schedule`).

## Watching the live site

| Check | Where | Alerts |
|---|---|---|
| Every Dot host's `/up` and `/.env`, plus InfoDot's `/ready` (database, cache, storage, scheduler) | `uptime.yml` (scheduled) | the run goes red |
| Host disk, cron, logs | `host-health.yml`, `server-logs.yml` | the run goes red |
| Application checks (failed jobs, waiting jobs, outboxes, heartbeat) | `php artisan ops:check` / `ops:alert` (every 10 minutes) | email to `OPS_ALERT_ADDRESS` once the owner sets it (owner item O-02) |
| Errors | Sentry, once the owner sets the DSN (owner item O-02) | Sentry |
| Lazy loading (N+1) on live pages | `performance.lazy_load` lines in the log | none (read them when tuning) |

## Dependencies

Dependabot (`.github/dependabot.yml`) opens grouped weekly pull requests for Composer and npm and monthly ones for GitHub
Actions; security fixes arrive at once. CI blocks high and critical advisories (Composer: medium and unrated too). Merge them
like any other change.

## Other runbooks

- `runbooks/operational-continuity.md`: who can do what, where every credential lives, how another person takes over.
- `runbooks/mysql-restore-drill.md`: the restore drill (owner: create a scratch database first).
- `runbooks/paystack-live-verification.md`: switching card payments on.
- `runbooks/owner-actions-2026-11.md`: the owner's open items.
- `../deployment-cpanel.md`: the host layout, docroot and app-folder guard.

## Owner items that no change in this repository can do

O-01 restore drill (scratch database), O-02 Sentry DSN and `OPS_ALERT_ADDRESS`, O-09 a cloud backup destination and the
passphrase in the password manager, O-13 the host's PHP version, O-14 an incident contact, O-15 a GitHub `production`
environment with required reviewers. Listed with steps in `R72_ENTERPRISE_AUDIT.md`.
