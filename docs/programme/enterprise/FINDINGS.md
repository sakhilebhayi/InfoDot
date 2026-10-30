# InfoDot findings register (2026-10-30)

Source: three read-only code audits (access control and tenancy; input handling, integrations and secrets; reliability and performance), the tool baseline in [BASELINE.md](BASELINE.md), and live read-only checks of infodot.co.za. Code audited: `main` at `c89286f`. Nothing in this file was invented; where a claim rests on a reviewer's reading only, it says so.

## Related documents

`BASELINE.md`, `SCORECARD.md`, `READINESS_MODEL.md`; procedures in `policies/` (incident response, recovery drills, vendor register, risk register, change and access).

## Method

- **Severity.** *Critical*: a deterministic production failure on a core path, or an exploitable cross-tenant leak, account takeover or loss of money with no special conditions. *High*: the same outcomes but needing a condition (a hostile satellite, a double click, a slow call), or a defect that blinds operators (no error reporting). *Medium*: a bounded or defence-in-depth weakness, or a scale problem. *Low*: hygiene or latent. *Info*: noted.
- **Verified by.** **R** = I read the code path myself. **T** = I wrote a test that fails on the old code and passes on the fix. **A** = a reviewer read it and I did not re-confirm it independently. **L** = checked live (read-only GETs).
- **Status.** **Fixed (PR #161)**: fixed on branch `hardening/phase1-dependencies`, tested, **not yet merged or deployed**. **Open**: not fixed. **Owner**: needs a decision or an action on the host.
- Findings the reviewers raised that I judged not to be defects are listed at the end.

## Release blockers (readiness model, rule 3)

| Blocker | State |
|---|---|
| Payment or seat path that can double-refund or lose a payment | **Cleared on the branch** (C-03, C-04, C-05); open in production until deployed |
| Cross-tenant leak | None confirmed. A-02, A-04 were bounded exposures; fixed on the branch |
| Known critical or high vulnerability | A-01/B-02 (account takeover from a hostile satellite) fixed on the branch; open in production until deployed |
| Data loss with no tested restore | **OPEN.** C-10: the MySQL restore has never been run (needs the owner) |

## Register

| ID | Area | Finding | Sev | Evidence | Status |
|---|---|---|---|---|---|
| C-01 | Questions | After posting a question the controller flashed `Questions::all()` into the session. The session payload column is `TEXT` (about 64 KB), so enough long questions make the session write fail after the question is saved; the person retries and duplicates it | Critical | R, T: `QuestionsController.php:99-102`; `2021_12_20_183649_create_sessions_table.php:21`; test fails on old code. Production size of the table not checked | Fixed (PR #161) |
| A-01 / B-02 | SSO | `/auth/ecosystem` on InfoDot accepted any `ecosystem:read` token, including one minted for a satellite (audience not checked), so a hostile satellite could replay a launch token and get a full InfoDot session without two-factor. `/api/ecosystem/token` could mint more tokens from a handoff token without limit | High | R, T: `EcosystemAuthController.php`, `EcosystemTokenController.php` | Fixed (PR #161) |
| C-03 | Seminar money | A host could submit a partial refund twice; each got a new key and went to Billing as a separate refund | High | R, T: `SeminarService::hostRefund` | Fixed (PR #161). Whether Billing caps over-refunds: not verified |
| C-04 | Seminar money | A double click on "buy" created a second ticket reference while the first request waited for Billing; a payment on the first matched no registration | High | R, T: `SeminarService::startCheckout` | Fixed (PR #161) |
| C-05 | Seminar money | The hold sweep read expired holds then wrote them without re-checking; a payment confirmed in between was overwritten as cancelled | High | R, T: `SeminarService::expireHolds` | Fixed (PR #161) |
| C-06 | Observability | `sentry/sentry-laravel` installed, nothing sent it exceptions | High | R, T: `Handler::register` was empty | Fixed (PR #161). Production DSN and alert routing: Owner |
| C-02 | Scheduler | All 15 scheduled tasks used Laravel's default 24-hour overlap lock. The cron runner kills a pass after 115 s; a killed run never releases its lock, silencing that task for a day (points delivery, refund retries, digests) | High | A for the mechanism, R for the 15 uses, T for the new bound. Real run times not measured | Fixed (PR #161) for the lock; durations Open |
| C-07 | Database | The visibility rule on every question/solution query had no index on `visibility` or `user_id`; `team_user.user_id`, the 5-minute payment sweep, notice pruning and p2p signals were unindexed | High at scale | A; indexes verified on a fresh database | Fixed (PR #161), migration `2026_10_30_100001`. Query timings on production data: not measured |
| C-08 | Lists | Question and solution lists loaded every visible row with three correlated counts | High at scale | R | Fixed (PR #161): latest 200, with a notice. Other lists (analytics per member, search) Open |
| C-09 | Rooms | Small-room polling takes a row lock per poll; 200 viewers is tens of requests a second serialised on one row, on an account limited to 30 processes | High | A. **Not load-tested** | Open |
| C-10 | Backups | InfoDot's MySQL dump has never been restored; the only restore tests were SQLite apps; first overnight run and Mac pull not observed; passphrase copy not evidenced; the doc contradicts itself on whether it ran | High / blocker | A, from `docs/programme/BACKUPS.md` | **Open, Owner** |
| C-11 | Knowledge | Two simultaneous accepts of different answers could both succeed (no lock) and credit twice | High (credit) | A for the race; R for the missing lock | Fixed (PR #161). Whether Dopemine de-duplicates: not verified |
| A-02 | Tenancy | Livewire restores models without visibility scopes; a removed member with an open page could still read new comments/answers and post | Medium | R, T: `Comments`, `Answers` | Fixed (PR #161) |
| A-03 | Licensing | A personal workspace could invite unlimited people (seat limits skip it) and share company-only content. An existing test asserted five invitations succeeded | Medium | R, T | Fixed (PR #161) |
| A-04 | Moderation | The moderator queue printed reporter names, report text and appeal text for another company's private items | Medium | R: `queue.blade.php` | Fixed (PR #161) |
| A-05 | Rooms | LiveKit join tokens could not be revoked and lasted until the seminar ended plus 15 minutes; a removed, refunded or charged-back attendee kept access with the token they held | Medium | A | Mitigated (this PR): tokens now last 10 minutes at most (`SEMINARS_VIDEO_TOKEN_TTL_MINUTES`), still capped at the seminar's end plus 15. The page fetches a fresh, re-checked token when it reconnects or is reloaded. A person already in the room can stay until they disconnect: removal still disconnects them |
| A-06 | Seminar moderation | A moderator could decide appeals or dismiss reports about their own seminar (content moderation forbids this); a draft could be removed | Medium | R, T (my code from #158) | Fixed (PR #161) |
| A-07 | Teams | Any member could cancel a company's pending invitations from the browser (Jetstream's action checks nothing) | Medium | R (vendor code), T | Fixed (PR #161) |
| A-08 / B-05 | SSO | A GET `/auth/ecosystem?token=` signed the visitor in without the origin check (login CSRF) | Medium | R, T | Fixed (PR #161) |
| A-09 | Livewire | `kind`/`itemId`/`solutionId` were client-settable (existence oracle, moderator metadata) | Low | R, T | Fixed (PR #161) |
| A-10 / B-04 | API | `/api/user` returned the whole user model including `two_factor_secret` and recovery codes | Medium | R, T | Fixed (PR #161) |
| A-11 | Channels | `Broadcast::channel('question', fn () => true)`; the dispatch is commented out | Low (latent) | A | Open |
| A-12 | Search | Scout indexes include company-only content; nothing queries them | Low (latent) | A | Open |
| A-13 | Models | Wide `$fillable` on Seminar, SeminarRegistration, Team, Questions; nothing reachable from input today | Low (latent) | A | Open |
| B-01 | Secrets | A 32-byte `APP_KEY` and Pusher credentials were committed in `.env.production.example` (since March 2026) | Low (was High if live) | R for presence and length. **Owner check run 2026-10-30:** a hash comparison on the host showed the committed `APP_KEY` is **not** the live key (no value was printed) | Values removed (PR #161). Resolved for `APP_KEY`. **Open, Owner:** the committed Pusher key/secret were not compared; if the host's `.env` uses them, rotate them. History is not rewritten (not needed for a retired key) |
| B-03 | Email links | Reset/verify/invitation links took their host from the request `Host` header | Medium | A; fix tested | Fixed (PR #161): a request for a Host that is not the site's own (or its www form) gets links fixed to `APP_URL`. **A first version forced the apex for www visitors too and would have broken login on `www.infodot.co.za` (the site answers there and forms post to absolute URLs); caught by the Perch scan, fixed and tested** |
| B-06 | Auth forms | Register, forgot-password, reset-password had no rate limit | Medium | A; fix tested | Fixed (PR #161) |
| B-07 | Input | No size limits on question/solution text, step bodies, comments; no throttle on solution creation, comments, solution search | Medium | R | Fixed (PR #161); the Livewire header search fan-out is limited to 40 queries a minute per person (this PR) |
| B-08 | Headers | No security headers on the live site | Medium | **L** (GET /): no HSTS, X-Frame-Options, nosniff, referrer or permissions policy | Fixed (PR #161) except CSP: Open (needs a reporting period) |
| B-09 | Throttling | One IP-keyed bucket shared by SSO redeem, Billing and LiveKit callbacks, ahead of signature checks | Medium | A | Fixed (PR #161): separate limiters. Trusted-proxy setup: Owner (`TrustProxies::$proxies` is unset) |
| B-10 | Stripe | Cashier's `/stripe/webhook` registered, unsigned when no secret | Low | A; fix tested | Fixed (PR #161) |
| B-11 | SSRF | Link checker pins DNS for port 443 only; a redirect to another port is resolved by curl | Low | A | Open |
| B-12 | Phishing | A host's meeting link is an arbitrary https URL that attendees are redirected to | Low | A | Open (report path exists) |
| B-13 | Throttling | 38 `throttle:N,M` routes shared one counter per person | Low | R, T | Fixed (PR #161), guard test for any new one |
| B-14 | Signing | HMAC covers timestamp and body but not method/path/query; no nonce | Low | A | Open (documented in the OpenAPI file) |
| B-15 | Privacy | Avatar URL exposes an unsalted MD5 of the email | Low | A | Open |
| B-16 | Config | `.env.example` defaults: debug on, guessable websocket credentials | Low | A | Fixed for the production example (PR #161); `.env.example` Open |
| B-17 | Hygiene | Meilisearch data files tracked | Low | A | Fixed (PR #161) |
| C-12 | Search | Related-question and header search use `LIKE '%x%'` on long text per page view / keystroke burst; FULLTEXT used only on the results page | Medium | A | Open |
| C-13 | Seminars | Cancelling a seminar makes one Billing call per paid registration inside one request, unlocked; a timeout leaves later tickets unrefunded; two concurrent cancels double-send | Medium | A | Mitigated (this PR): every refund is marked requested before the first call to Billing, so a cut-off request is finished by the sweep. Still one Billing call per ticket inside the request, and two concurrent cancels are unlocked |
| C-14 | Invoices | One deleted host stopped the whole monthly commission invoice run | Medium | R, T | Fixed (PR #161); a deleted host's commission is skipped and logged, not invoiced: Owner/adviser |
| C-15 | Callbacks | See B-09 | Medium | | Fixed (PR #161) |
| C-16 | Points | The outbox can send a row twice (no claim); the "earlier event" check is a LIKE scan per row | Medium | A | Open |
| C-17 | Points | Parked or abandoned outbox rows raise no alert; commands always report success | Medium | A | Fixed (this PR): `ops:check` runs every 15 minutes and reports parked and long-stale outbox rows once a day (log and Sentry). The flush commands still return success |
| C-18 | Seminars | Link check (DNS plus up to four 4 s hops) runs inside a database transaction | Medium | A | Open |
| C-19 | Seats | Blocking 3 s Billing call on every seat view/invite while Billing is down | Medium | A | Open |
| C-20 | Mail | SMTP is called synchronously in web requests with no timeout | Medium | A | Mitigated (this PR): SMTP timeout 10 s (`MAIL_TIMEOUT`). Mail is still sent inside the web request |
| C-21 | Seminars | Reminders and time-change notices do `User::find` per registrant | Medium | A | Open |
| C-22 | Reputation | Nightly recalculation about 25 queries per user, likely over 115 s; opt-out race; snapshots never pruned | Medium | A | Open |
| C-23 | Analytics | Dashboard runs a query per member and non-sargable month filters | Medium | A | Open |
| C-24 | Indexes | Missing indexes on `associates`, `comments`, `likes`, `objects` columns the code filters on (the `constrained()`/`contrained` calls created nothing) | Medium | A | Open (only the indexes in C-07 were added) |
| C-25 | Constraints | `seminar_reports` unique key was dropped for person reports; first-or-create races on reports, associates, debts | Low | A, R for the report key (my change) | Open |
| C-26 | Deadlocks | Lock order differs between checkout/cancel and the ticket callback | Low | A | Open |
| C-27 | Solutions | Creating a solution is not one transaction; unbounded tags could 500 | Low | A | Tags now capped (PR #161); transaction Open |
| C-28 | Logs | Single unrotated log file; no request id; example sets debug level | Medium | A | Rotation and request id fixed (this PR): daily files kept 14 days, every log line and the Sentry event carry `request_id`, the response has `X-Request-Id`. Logs are still plain text, not JSON |
| C-29 | Docs | `docs/deployment-cpanel.md` says "no cron lines" and PostgreSQL; production DB engine is stated inconsistently | Medium | A | Open |
| C-30 | N+1 | Per-row lookups in answers, notices; cross-platform search fan-out on every query; Points summary failures not cached | Low | A | Open |
| P-01 | Pipeline | `main` has no branch protection and no rulesets; required checks are not enforced | High (governance) | **L** via GitHub API: "Branch not protected", rulesets `[]` | Open, Owner (changes how merges work) |
| P-02 | Pipeline | Dependabot security updates are disabled | Medium | **L** | Open, Owner |
| P-03 | Pipeline | `deploy.yml` says its gate runs "tests, Pint, frontend build + manifest check"; `ci.yml` ran tests only | Medium | R | Fixed (PR #161): the gate now runs advisories, PHPStan, Pint, frontend build and tests. The manifest check is still not in `ci.yml` |
| P-04 | Pipeline | Coverage was never measured | Medium | R | Fixed (PR #161): CI measures it. **94.0% of lines in `app/`** |
| P-05 | Pipeline | PHPStan level 5 was configured but not run in CI; 173 findings | Medium | R, tool run | Fixed (PR #161): baseline file, CI gate. This PR: documenting the Questions/Solutions columns cut the legacy debt to **154**, and `phpstan-baseline.max` plus `tools/check-phpstan-baseline.sh` make the ceiling enforced (it can only go down) |
| P-06 | Pipeline | Pint failed on 39 files | Low | tool run | Fixed (PR #161) |
| P-07 | Dependencies | symfony/mime 7.4.19 (CVE-2026-45070, medium); 2 npm moderate (build-time) | Medium | tool run | Fixed (PR #161); composer audit 0, npm audit 0 |
| P-08 | Repo | `.phpunit.cache/test-results` is tracked and dirties every run | Info | R | Open |
| O-01 | Observability | A dead scheduler stops points delivery, refund retries and digests with no error. `/ready` now reports `degraded` when the scheduler heartbeat (written every minute) is older than 15 minutes; an outside monitor must call `/ready` for this to reach a person | Medium | R, T | Fixed (this PR) in code. **Verified on production 2026-10-30** (heartbeat 0 minutes old, `ops:check` clean: `evidence/2026-10-30-ops-checks.md`). **Owner:** point an uptime monitor at `https://infodot.co.za/ready` |
| O-02 | Incident control | The only switch that stops new paid checkouts is `SEMINARS_PAID_ENABLED=false`, which also stops all refund requests to Billing and makes Billing's callbacks answer 401, so using it mid-incident can strand in-flight payments | Medium | R: `BillingMarketplace::configured`, `SeminarTicketEventsController` | Open (engineering: a narrower switch). Documented in `policies/INCIDENT_RESPONSE.md` |
| P-09 | Deploy | A failed deploy after maintenance mode starts leaves the site down by design; the rollback copy is manual and not drilled | Medium | R | Open, Owner |

## Access checks that held (tested, no finding)

`tests/Feature/Hardening/SeminarAccessMatrixTest.php` runs the paid-seminar routes as the host, a ticket holder, a stranger, another host and a signed-out visitor. Edit, update, cancel, attendance and refund are host-only (403, and nothing changes and Billing is never called); a refund aimed at another host's seminar is 404; the page shows ratings and names to the host only and never prints the meeting link; receipts are the buyer's; invoices, debts and account digits are the host's own; the data download holds only the caller's data; moderation screens are moderator-only; every page redirects a signed-out visitor. No gap was found.

## Raised by reviewers and not carried into the register

- *Cross-tenant data leak through the main question, solution, seminar, receipt, invoice or export paths*: the access-control reviewer found none; the global scopes and host/buyer checks held (it lists what it checked).
- *XSS, SQL injection, unsafe file uploads, webhook-signature holes*: the input reviewer found none and lists the files read.
- My own first assumption that `/up` does not check the database was wrong: it runs `select 1` (`routes/web.php`). `/ready` was added for the cache and storage checks anyway.
