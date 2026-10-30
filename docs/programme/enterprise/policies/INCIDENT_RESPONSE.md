# Incident response (draft procedure)

Applies to InfoDot (infodot.co.za). Other Dot platforms share the host and the crontab, so an incident on one can be an incident on all; check `docs/programme/shared-crontab-race` notes in the owner's memory file before touching cron.

## What counts as an incident

Treat it as an incident, not a bug, when any of these is true: people cannot sign in or buy; money moved wrongly (a double charge or refund, a ticket paid with no registration); someone saw another company's or person's data; a secret may have been exposed; the site is serving something it should not; a backup or restore failed.

## Roles

| Role | Who |
|---|---|
| Incident lead (decides, talks to people affected) | **[OWNER]** |
| Technical responder | Claude Code sessions working for the owner, under the owner's instruction; deploys and host commands are the owner's call |
| Information Officer (POPIA) | **[OWNER]** (named in the privacy policy as `privacy@infodot.co.za`; registration with the Information Regulator is an open owner item) |
| Adviser (legal) | **[OWNER]** |

## The first fifteen minutes

1. **Write down the time and what was seen**, in your own words, before changing anything.
2. **Is the site up?** `curl -s https://infodot.co.za/up` (OK = the app and database answer) and `curl -s https://infodot.co.za/ready` (`ok` or `degraded`; degraded means the database, cache, storage or the scheduler heartbeat is bad). Every response carries `X-Request-Id`; quote it.
3. **What is failing silently?** On the host, in the app folder: `php artisan ops:check` (counts of parked or stale outbox events, unconfirmed refunds, unreleased holds).
4. **Look at the logs without reading personal data.** The `Server logs` workflow (`server-logs.yml`) prints the newest `storage/logs/*.log`. Logs carry `request_id`; search for it. If Sentry has a DSN, the same id is a tag on the event.
5. **Contain, smallest step first.**
   * A bad release: re-run the previous release, or restore the rollback copy (see RECOVERY_DRILL.md; the three newest copies are in the home folder as `dot-infodot-*.tgz`). Rolling code back does not undo migrations; the recent ones are additive.
   * Payments going wrong: **`SEMINARS_CHECKOUT_ENABLED=false`** (then `php artisan config:cache`) pauses new ticket sales only; refunds, Billing's callbacks and tickets already bought keep working. Use this first. `SEMINARS_PAID_ENABLED=false` is the blunt switch: it also stops every refund request to Billing and makes the callback endpoint answer 401, so a payment completed while it is off creates no registration until it is on again and Billing resends (whether Billing resends after a 401 is **not verified**). Use it only to stop a runaway such as refunds being sent twice, note the time, and switch it back as soon as the cause is fixed.
   * Rooms: `SEMINARS_VIDEO_ENABLED=false` / `SEMINARS_P2P_ENABLED=false`.
   * A leaked key or token: rotate it. For `APP_KEY` this signs everyone out and invalidates encrypted values (2FA secrets are encrypted with it), so plan it. A leaked Billing or callback secret must be changed in both systems at once.
   * Maintenance mode if needed: `php artisan down --retry=60`.
6. **Do not delete evidence**: keep logs, the rollback copy and the database as they are until the cause is known.

## Telling people

| Situation | Tell | When |
|---|---|---|
| Personal information may have been accessed or acquired by someone not authorised | The Information Regulator and the people affected (POPIA s22), via the Information Officer | "As soon as reasonably possible" after discovery. **[OWNER/ADVISER]** decides whether the threshold is met and the wording; this draft does not decide it |
| Money moved wrongly | The people affected, the payment provider contact, and the adviser if more than a few tickets | Same day |
| Outage longer than an hour | People with a seminar in the next 24 hours, by the in-app notice or the host's own channel | As soon as the cause is known |
| Another platform's data is involved | That platform's owner (the same person today) | Immediately |

## After it

Write a short record in `docs/programme/EXECUTION_LOG.md`: what happened, how it was detected (by whom or what), how long until detected and until fixed, what was lost, what changes now. Add or update a row in `FINDINGS.md`. Where a check could have caught it earlier, add the check and a test.

## What does not exist yet

* An alert that reaches a person: the checks exist (`/ready`, `ops:check`, Sentry events once a DSN is set) but nothing pages anyone until the owner sets up a monitor and a Sentry alert rule. **[OWNER]**
* A tested restore of InfoDot's MySQL database (RECOVERY_DRILL.md, drill B).
* A rehearsal of this procedure. Run one tabletop pass (read the steps aloud against an invented incident) and record the date here: **[not yet run]**.
