# Platform go-live checklist

For one platform at a time. Everything here is something only the account owner can do (DNS, cPanel,
Paystack, GitHub secrets); the code side is merged. `<key>` is the platform key (`tasks`, `auction`, ...)
and `<host>` is `<key>.infodot.co.za`. Each repo's `docs/deployment.md` has the same steps in more detail.

Secrets: run `tools/ecosystem-secrets.sh` once. It writes `~/dot-ecosystem-secrets.env` (mode 600)
with every shared value grouped by "paste this into that app". Never paste its contents into chat,
tickets or commits.

## 1. Hosting (cPanel)
1. DNS: an A record for `<host>` to the shared host (198.38.92.233). Seven names currently serve only a
   Coming Soon page and seven have no DNS at all (see the register, section 1).
2. cPanel: create the subdomain and its docroot, then a database and user for the app (SQLite also works
   for small apps: Dot.Farms has only ever run on SQLite). Create the app folder with a production
   `.env` (`APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://<host>`, database credentials,
   SMTP, `APP_KEY` from `php artisan key:generate --show`). **Back up `APP_KEY`: Notify, HR, Ehail and
   Analytics encrypt data with it.**
3. GitHub repository secrets on the platform repo: `DEPLOY_HOST`, `DEPLOY_USER`, `DEPLOY_SSH_KEY`,
   `DEPLOY_KNOWN_HOSTS` (and `DEPLOY_PATH` if the app is not the only Laravel app under the docroot).
4. Run the workflow once as **adopt**, then as **deploy**: `gh workflow run deploy.yml -f confirm=adopt`
   then `-f confirm=deploy`. A red run leaves the site in maintenance mode on purpose; read the log and
   re-run. The workflow installs the cron lines (schedule runner + cron-driven queue worker) under the
   shared lock; do not edit the shared crontab by hand.
5. Smoke: `https://<host>/up` answers 200 and `POST /up` answers 405.

## 2. Sign-in through InfoDot
1. Secrets block 1: `ECOSYSTEM_SECRET_<KEY>` in InfoDot's `.env`, and `INFODOT_SERVICE_SECRET` +
   `INFODOT_PLATFORM_KEY=<key>` in the app (same value).
2. Mark the platform live in InfoDot: set `'active' => true` for `<key>` in `config/ecosystem.php`
   (InfoDot refuses sign-in redeems for platforms that are not live) and deploy InfoDot. Ask Claude to
   open that PR when a host is ready.
3. Link existing companies before their people sign in (otherwise a duplicate team is created):
   `php artisan infodot:link-team <company-uuid> --promote` on the app.

## 3. Optional links
- Notify (secrets block 2): events only flow when the company also enables them on Notify's
  Connected apps page (or `php artisan notify:enable-defaults <company-uuid> <key>`). Set each
  platform's `DOT_<KEY>_URL` in Notify's `.env` to its real host, otherwise links in emails are dropped.
- Analytics (secrets block 3): try `php artisan <app>:publish-metrics --dry-run` first.
- A queue worker is required for both (the deploy installs the cron-driven one).

## 4. Paystack (Billing, Emall, Auction, Ehail), TEST MODE ONLY
Nothing in the code has made a real Paystack call yet. One real test payment per app is the
acceptance test. Give each app its own Paystack business: a business has one webhook URL per mode, so
apps sharing one would receive each other's events (recorded as `unmatched`, harmless but useless).

1. Paystack dashboard, **test mode**, Settings, API Keys & Webhooks: copy the test secret key
   (`sk_test_...`) and public key (`pk_test_...`).
2. App `.env`: `PAYSTACK_SECRET_KEY=sk_test_...`, `PAYSTACK_PUBLIC_KEY=pk_test_...`. Leave
   `PAYSTACK_ALLOW_LIVE=false`; a `sk_live_` key is refused by design. Emall: set `SHOP_CURRENCY`
   (default ZAR) once its currency PR is merged.
3. Paystack, test webhook URL: `https://<host>/webhooks/paystack`.
4. Run one payment with a test card from Paystack's documentation:
   - Billing: open an invoice, "Pay now".
   - Emall: place an order and pay.
   - Auction: win an auction (or buy now) and "Pay now" on the settlement.
   - Ehail: complete a ride with a fare and "Pay fare".
   Confirm: the page shows paid, the operator payments page shows the event as applied, and replaying the
   webhook from the Paystack dashboard changes nothing.
5. Not built (decisions needed before any live key): seller, driver and fleet payouts, commissions and
   splits, KYC, saved cards, tips.

## 5. Operators
`users.is_platform_operator` gates the operator pages (Ehail, Design, Tutor, Emall, Analytics, Billing,
Auction once its flag PR is merged). Grant it from the server console of that app, e.g.
`php artisan analytics:operator you@example.com` (Analytics) or the equivalent command in the PR notes.

## 6. Before real data
Back up the database before each deploy that contains migrations (several rewrite data in place; the
runbook lists which). Review the retention default in HR (3 years, a starting figure, not legal advice)
and the privacy, cookie and policy text in each repo with whoever advises you on POPIA.
