# Platform merge and deploy runbook

Written 2026-10-18 after the platform completion waves. Everything below is an open pull
request: **nothing is merged or deployed**. This is the order to merge, and what has to
exist before each platform can go live. Test counts are from my own re-runs on SQLite
(some agents also ran Postgres 16); nothing has run on MySQL or against production data.

Rules that apply to every platform:

1. Merge a stack **bottom-up**: each PR is based on the one before it. After a PR merges,
   GitHub retargets the next one; re-check it shows MERGEABLE.
2. **Back up the database before migrating** (several migrations rewrite data in place).
3. A platform cannot sign anyone in until: its DNS and database exist, `ECOSYSTEM_SECRET_<KEY>`
   is set in InfoDot (InfoDot PR #32 lists the keys), the same value is set as
   `INFODOT_SERVICE_SECRET` with `INFODOT_PLATFORM_KEY=<key>` in the app, and the platform is
   marked live in InfoDot's registry. Then run `php artisan infodot:link-team <uuid> --promote`
   for existing companies *before* their people sign in, or a duplicate team is created.
4. The shared cPanel crontab must contain `* * * * * php artisan schedule:run` for each app that
   schedules work, and a queue worker (`queue:work`) where listed. Do not overwrite the shared
   crontab by hand.
5. Do not rotate `APP_KEY` after encrypting data (Notify, HR, Ehail, Analytics).
6. CI: each repo has a "CI: run tests on every pull request" PR. Merge it first so later PRs
   show a status check.

## InfoDot

- [#31](https://github.com/sakhilebhayi/InfoDot/pull/31) docs (register, this runbook), [#32](https://github.com/sakhilebhayi/InfoDot/pull/32) redeem secrets for the 14 platforms. Inert until the secrets are set.

## Per platform (merge in this order)

| Platform | Merge order | Needs before live | Backups and cautions |
|---|---|---|---|
| Analytics | CI is already in. [#12](https://github.com/sakhilebhayi/Dot.Analytics/pull/12) → [#13](https://github.com/sakhilebhayi/Dot.Analytics/pull/13) → [#14](https://github.com/sakhilebhayi/Dot.Analytics/pull/14). Trunk is `feature/ecosystem-sso` (main is a stub). | `ECOSYSTEM_SECRET_ANALYTICS`; run `php artisan analytics:operator <email>`; issue webhook secrets to connected platforms (unsigned ingest now 401). | Rotate any plaintext webhook secrets. Migrations encrypt secrets and scrub audit logs. |
| Auction | [#3](https://github.com/sakhilebhayi/Dot.Auction/pull/3) → [#4](https://github.com/sakhilebhayi/Dot.Auction/pull/4) → [#6](https://github.com/sakhilebhayi/Dot.Auction/pull/6) → [#7](https://github.com/sakhilebhayi/Dot.Auction/pull/7); [#5](https://github.com/sakhilebhayi/Dot.Auction/pull/5) CI. Base is the unmerged `feat/reverb-realtime-core`. | Queue worker; scheduler (settle and ending-soon); Reverb; `storage:link` and PHP `gd`. | #3 migration demotes duplicate winning bids; read it first. No payment: settlement writes an "awaiting payment" row only. |
| Billing | [#2](https://github.com/sakhilebhayi/Dot.Billing/pull/2) → [#4](https://github.com/sakhilebhayi/Dot.Billing/pull/4) → [#5](https://github.com/sakhilebhayi/Dot.Billing/pull/5) → [#6](https://github.com/sakhilebhayi/Dot.Billing/pull/6); [#3](https://github.com/sakhilebhayi/Dot.Billing/pull/3) CI. | Queue worker; scheduler (advance subscriptions, generate invoices, scan alerts); `BILLING_ENTITLEMENT_SECRET_<KEY>` per caller; `NOTIFY_INGEST_*`; real free-tier limits, tax rate, issuer details. | 8 migrations (cents backfill, constraints). Team deletion is now blocked when billing history exists. Owner decisions: billing model, erasure policy. |
| Design | [#4](https://github.com/sakhilebhayi/Dot.Design/pull/4) → [#5](https://github.com/sakhilebhayi/Dot.Design/pull/5); [#6](https://github.com/sakhilebhayi/Dot.Design/pull/6) CI. | Set `users.is_platform_operator` for catalogue editors. | Dedupe migration renumbers duplicate page numbers. AI images are a labelled placeholder. |
| Ehail | [#3](https://github.com/sakhilebhayi/Dot.Ehail/pull/3) → [#4](https://github.com/sakhilebhayi/Dot.Ehail/pull/4) → [#6](https://github.com/sakhilebhayi/Dot.Ehail/pull/6) → [#7](https://github.com/sakhilebhayi/Dot.Ehail/pull/7) → [#8](https://github.com/sakhilebhayi/Dot.Ehail/pull/8); [#5](https://github.com/sakhilebhayi/Dot.Ehail/pull/5) CI. | Scheduler cron (ride expiry); Reverb; queue worker; set `is_platform_operator`. | Back up before each of #6, #7, #8. Audit production for driver profiles self-approved before #3. #8 encrypts PII. |
| Emall | [#4](https://github.com/sakhilebhayi/Dot.Emall/pull/4) → [#6](https://github.com/sakhilebhayi/Dot.Emall/pull/6) → [#7](https://github.com/sakhilebhayi/Dot.Emall/pull/7) → [#8](https://github.com/sakhilebhayi/Dot.Emall/pull/8); [#5](https://github.com/sakhilebhayi/Dot.Emall/pull/5) CI. | `storage:link`, PHP `gd`; back up the photo folder. | #6 rewrites order totals and statuses (not reversible). #7 upper-cases promo codes. No payment: orders are pending, unpaid. |
| Farms | [#2](https://github.com/sakhilebhayi/Dot.Farms/pull/2) → [#4](https://github.com/sakhilebhayi/Dot.Farms/pull/4) → [#5](https://github.com/sakhilebhayi/Dot.Farms/pull/5); [#3](https://github.com/sakhilebhayi/Dot.Farms/pull/3) CI. Base is the unmerged `feat/reverb-realtime-core`. | Reverb; queue worker. | #4 rewrites unit values in two tables. The demo seeder refuses to run outside local/testing. |
| Finance | [#4](https://github.com/sakhilebhayi/Dot.Finance/pull/4) → [#6](https://github.com/sakhilebhayi/Dot.Finance/pull/6) → [#7](https://github.com/sakhilebhayi/Dot.Finance/pull/7) → [#8](https://github.com/sakhilebhayi/Dot.Finance/pull/8); [#5](https://github.com/sakhilebhayi/Dot.Finance/pull/5) CI. | Scheduler (recurring items, insights, prune imports). | Run migrations in maintenance mode, then `finance:check-balances`. Opening balance is dated the migration day. Owner decision: personal vs company finance. |
| HR | [#2](https://github.com/sakhilebhayi/Dot.HR/pull/2) → [#4](https://github.com/sakhilebhayi/Dot.HR/pull/4); [#3](https://github.com/sakhilebhayi/Dot.HR/pull/3) CI. | Scheduler (`hr:purge-expired`); link employees to accounts. | Last migration encrypts PII in place: back up, keep `APP_KEY`. Editors lose contact details and leave reasons. Retention default 3 years is a starting figure, not legal advice. |
| Notify | [#2](https://github.com/sakhilebhayi/Dot.Notify/pull/2) → [#3](https://github.com/sakhilebhayi/Dot.Notify/pull/3) → [#5](https://github.com/sakhilebhayi/Dot.Notify/pull/5); [#4](https://github.com/sakhilebhayi/Dot.Notify/pull/4) CI. Base is the unmerged `feat/reverb-realtime-core`. | **Queue worker (required for delivery)**; scheduler; `NOTIFY_INGEST_SECRET_<KEY>` per calling platform. | Back up before the encryption migration. Privacy and cookie text was edited (it claimed tracking pixels): owner to read. |
| Plug | [#3](https://github.com/sakhilebhayi/Dot.Plug/pull/3) → [#4](https://github.com/sakhilebhayi/Dot.Plug/pull/4) → [#6](https://github.com/sakhilebhayi/Dot.Plug/pull/6); [#5](https://github.com/sakhilebhayi/Dot.Plug/pull/5) CI. | Nothing special. | Non-admins can no longer install or publish. |
| Pulse | [#5](https://github.com/sakhilebhayi/Dot.Pulse/pull/5) → [#6](https://github.com/sakhilebhayi/Dot.Pulse/pull/6) → [#7](https://github.com/sakhilebhayi/Dot.Pulse/pull/7) → [#8](https://github.com/sakhilebhayi/Dot.Pulse/pull/8); [#4](https://github.com/sakhilebhayi/Dot.Pulse/pull/4) CI. `main` is canonical; `feature/ecosystem-sso` is archived. | **Queue worker**, scheduler (`pulse:sweep-pending`), Reverb, `ANTHROPIC_API_KEY` (without it every post is held for a human). | Moderation now fails closed; posts wait for review without a worker. |
| Tasks | [#3](https://github.com/sakhilebhayi/Dot.Tasks/pull/3) → [#4](https://github.com/sakhilebhayi/Dot.Tasks/pull/4) → [#5](https://github.com/sakhilebhayi/Dot.Tasks/pull/5) → [#7](https://github.com/sakhilebhayi/Dot.Tasks/pull/7) → [#8](https://github.com/sakhilebhayi/Dot.Tasks/pull/8) → [#9](https://github.com/sakhilebhayi/Dot.Tasks/pull/9); [#6](https://github.com/sakhilebhayi/Dot.Tasks/pull/6) CI. Base is the unmerged `feat/reverb-realtime-core`. | Scheduler (due-soon, escalation, recurrence); Reverb; queue worker. | **Check the production server first**: `app/Providers` and `app/Actions` were never in git (PR #5 restores them). |
| Tutor | [#3](https://github.com/sakhilebhayi/Dot.Tutor/pull/3) → [#5](https://github.com/sakhilebhayi/Dot.Tutor/pull/5); [#4](https://github.com/sakhilebhayi/Dot.Tutor/pull/4) CI. | Scheduler (expire stale sessions); run `SubjectCatalogueSeeder` once; set `is_platform_operator`. | Tailwind and Alpine still load from a CDN. No payments. |

## What is not built

Analytics real-data pipeline (connectors, pipelines, real KPIs); Design asset uploads and a real
AI provider; the apps calling Notify (Notify's ingest API is ready, Billing has a client);
payments for Billing, Emall, Auction and Ehail (provider undecided); restore screens for
soft-deleted records; mobile layout (several app shells use a fixed 260px sidebar).

## Not verified anywhere

A real InfoDot redeem call, real SMTP/Slack/Reverb/queue workers, MySQL, production data,
browser flows beyond desktop spot checks, and the `viewer`-role gap in Pulse, Emall, Finance,
Tutor, Design, Auction and Ehail (their data is per-user, so an InfoDot viewer mapped to
editor does not widen data access; Pulse is a global network).
