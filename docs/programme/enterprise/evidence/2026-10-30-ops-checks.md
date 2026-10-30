# Evidence: scheduler heartbeat and silent-failure check on production (2026-10-30)

Run by the owner on the host, in `/home/infodotc/public_html/main`, after the deploy of PR #163. Counts only; no data was read.

| Command | Result | What it shows |
|---|---|---|
| `php artisan tinker --execute="echo app(App\Services\Ops\OpsCheck::class)->minutesSinceHeartbeat() ?? 'never seen';"` | `0` | The cron reaches InfoDot's `schedule:run` and the heartbeat task ran within the last minute |
| `php artisan ops:check` | all eight kinds `ok`, count 0 | No parked or stale points, Memory or library events; no refund unconfirmed for 24 hours; no hold unreleased for an hour |

`GET https://infodot.co.za/ready` returned `{"status":"ok"}` (200) and `/up` returned `OK` (200) at deploy time.

Not shown by this evidence: that an outside monitor calls `/ready`, that a Sentry DSN is set, or how long the scheduled commands take to run.
