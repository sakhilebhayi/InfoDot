# Platform completion register

Started 2026-10-18. Owner: Sakhile. This is the shared progress register for the
14-platform completion mission. Update it in the same change as the work it records.
Status words: **Working / Partially / Broken / Missing / Not Verified**.
"Not Verified" means code reading only: nothing was observed running.

Evidence rule: a row only moves to Working with a test run, a live request or a
screenshot cited next to it.

## 1. Inventory and deployment register

Audit date 2026-10-18. Local folders are in `~/Dot/Dot.<Name>`. All are Laravel
(Jetstream, Livewire). "Test run" is a fresh clone of `origin/main` run against
in-memory SQLite by the audit (not the production database).

| Platform | What it really is | Local branch vs origin/main | Live host (probe 2026-10-18) | CI / deploy pipeline |
|---|---|---|---|---|
| Analytics | Insight dashboards, intelligence "engines", ingest API | real trunk is `feature/ecosystem-sso` (origin/main is a 4-commit stub; remote HEAD points at the trunk) | no DNS | CI yes (tests, no deploy) |
| Pulse | Global social network (not business engagement) | main = origin/main; `feature/ecosystem-sso` diverged by 28/28 commits | no DNS | none |
| Finance | Personal ledger (accounts, budgets, CSV, recurring) | main = origin/main (audit's "unpushed" claim was stale; merged as #2) | placeholder | none |
| Emall | Multi-vendor storefront, no payment | main = origin/main (merged as #2) | placeholder | none |
| Auction | Live bidding, proxy engine (dead code) | `feat/reverb-realtime-core`, 3 commits unmerged | placeholder | none |
| Billing | Read-only billing dashboard + dunning queue | main = origin/main | no DNS | none |
| Tasks | Kanban tasks, recurrence, escalation queue | `feat/reverb-realtime-core`, 3 unmerged, 1 behind | placeholder | none |
| HR | Employees + leave requests (small CRUD) | main = origin/main | no DNS | none |
| Plug | Extension marketplace (not integration config) | main = origin/main | no DNS | none |
| Email | **Dropped by the owner 2026-10-18** (no code ever existed; mix-up with Ehail) | n/a | no DNS | n/a |
| Tutor | Tutor booking marketplace (not courses) | main = origin/main | placeholder | none |
| Design | Canvas editor (text and rectangles), SVG export, token catalog | main = origin/main (editor merged as #2) | placeholder | none |
| Notify | Per-team notification rules, channels, schedules, webhook receiver | `feat/reverb-realtime-core`, 3 unmerged | no DNS | none |
| Farms | Crop-cycle logbook | `feat/reverb-realtime-core`, 1 unmerged | no DNS | none |
| Ehail (ride-hailing) | Fleet, drivers, ride requests; not email | `feat/reverb-realtime-core`, 3 unmerged, main has moved | placeholder | none |

Placeholder = resolves to 198.38.92.233 and serves a static "Coming Soon" page
(`/up` 404). No DNS = the name does not resolve at all.

Baseline test runs: see section 7.

## 2. Findings that repeat on every platform

These are fixed once as a pattern and then rolled out.

1. **SSO does not match the ecosystem contract (13 of 13 audited apps).** Each
   satellite looks up a Sanctum token in its *own* database and logs in. That works
   only if every app shares InfoDot's `personal_access_tokens` table. Dot.Files,
   Docs and Agents use the signed redeem call with a per-platform secret. The token
   also travels in the URL, with no throttle and no session regeneration. Fix: port
   the Files/Docs/Agents redeem flow, add `ECOSYSTEM_SECRET_<KEY>` in InfoDot.
2. **No deploy pipeline anywhere** except Analytics CI (tests only). Nothing can
   be deployed or rolled back reproducibly.
3. **Hosting.** 7 hosts are placeholders and 7 names have no DNS. DNS, cPanel
   subdomains, databases and mail credentials are owner-only.
4. **Tenancy.** Company (team) scoping is real only in HR, Notify, Billing, Plug
   installs, Farms (partial), Tasks (policy-only). Finance, Emall, Auction, Tutor,
   Design, Pulse and Ehail are user- or marketplace-scoped.
5. **`HasTeamScope` fails open** with no user or team (jobs, console, seeders).
6. **Docs drift.** README/wiki claim Laravel 12, "unverified scaffolding", or
   features that do not exist, in every repo.
7. **Branch hygiene.** Five repos hold an unmerged `feat/reverb-realtime-core`
   branch with no PR (Auction, Tasks, Notify, Farms, Ehail). Analytics works on
   `feature/ecosystem-sso` as its trunk. Pulse `main` and `feature/ecosystem-sso`
   have diverged. (The first audit wrongly called Emall, Finance and Design commits
   "unpushed"; they were merged as #2 and the audit read stale remote refs.)
8. **Local `.env` files** use `APP_ENV=local`, `APP_DEBUG=true`, http URLs on
   `.co.za`, and Postgres. Production values are unknown and must be set by the
   deploy, never copied.
9. **Money handling:** decimal columns but float arithmetic and mixed-currency sums
   (Billing, Finance, Auction, Emall).
10. **Nobody calls Notify, Billing, Brain, Memory or Dopemine.** There are no
    integrations to protect from outages yet.

## 3. Per-platform feature matrix and plan (summary)

Top defects are ranked; the full audit reports are summarised here, not repeated.

### Notify (shared service, build first)
- Working: channel/template/rule CRUD, in-app bell, HMAC webhook receiver, schedules.
- Partially: email (plain text), rate ceiling (webhook path only), delivery status.
- Missing: service-authenticated ingest for Dot apps; retries/backoff; dedupe and
  idempotency; preference enforcement; SMS/push; batches.
- Broken: preferences table is never read.
- Risks: plaintext channel credentials and signing secrets; arbitrary webhook URL
  (SSRF), no timeout; synchronous send inside the scheduler.
- Plan: merge hardening branch; add `POST /api/ingest/events` signed with the
  platform secret; queue + retry + idempotency key; enforce preferences; encrypt
  secrets; SSO redeem.

### Billing
- Working: invoice list/detail, dunning queue (human-gated), cushion calculator.
- Missing: provider, webhooks and idempotency, invoice generation and numbering,
  renewals, entitlement checks, payment methods, credits.
- Broken: AI insight shows canned advice as real.
- Plan: decide provider (Paystack or PayFast likely, owner decision), webhook
  receiver with signature and idempotency table, unique provider ids, entitlement
  endpoint, Notify for dunning.

### Finance
- Working: transactions CRUD, budgets, dashboard, recurring, CSV preview.
- Broken: account balance is not derived from transactions.
- Missing: forecasts, cash flow, P&L, audit trail, edit screens.
- Plan: ledger-derived balances, integer minor units, audit and soft deletes,
  company scoping decision (personal vs business) from owner.

### Auction
- Broken: bid placement is not atomic (double winners, lost price updates).
- Missing: seller listing creation, anti-snipe, payment handoff to Billing.
- Partially: settlement, reserve review; proxy engine is dead and untested.
- Plan: transaction + row lock + partial unique index on the winning bid, tests
  for concurrency, wire or remove the proxy engine, listing creation UI.

### Emall
- Working: browse, cart, checkout (transaction, stock locks), seller store,
  moderation, promo codes, fulfilment.
- Missing: payment, product images, restock on cancel.
- Broken: `pending_approval` default hides existing listings after migrate.
- Plan: payment via Billing (after provider decision), order-line snapshots, restock.

### Tasks
- Working: boards, subtasks, recurrence, smart views, escalation queue.
- Broken (likely): AI breakdown sends wrong auth headers and the mock hides it.
- Partially: notifications, search, labels. Missing: Projects link, API.
- Plan: merge branch, validate status in moveTask, team scope trait, fix headers.

### HR
- Working: employees, leave workflow (basic), dashboard.
- Missing: documents, onboarding, org chart, self-service leave, audit trail.
- Risks: any member sees all leave reasons; no retention policy.
- Plan: roles, audit log, pending-state guard, encrypted fields, self-service.

### Plug
- Working: marketplace listing, install status rows.
- Missing: connection and credential management, webhooks, API contracts, sandbox.
- Risks: install/uninstall has no role check.
- Plan: stays an extension marketplace (owner, 2026-10-18); role check first, then publish/version flow.

### Tutor
- Working: browse/book/confirm/complete, ratings, lesson files, operator approval.
- Missing: tutor onboarding, courses, progress, assessments, payments.
- Plan: stays a booking marketplace (owner, 2026-10-18); tutor onboarding, availability and overlap checks next; payments wait for the provider decision.

### Design
- Working: projects, basic editor, SVG export, token catalog.
- Missing: asset upload, real AI provider, collaboration, Files/Docs link.
- Broken/over-exposed: mock AI presented as real; `/design-system` writes open to any user.
- Plan: catalog authorization (PR open), uploads via Dot.Files, real AI provider later.

### Farms
- Working: farm/field/crop-cycle logbook, notification bell.
- Missing: inventory, scheduling, reporting, edit/delete.
- Plan: merge branch, state machine, edit/delete, then reporting.

### Pulse
- Working: feed, moderation hold queue, follows, reactions.
- Critical: global feed with no tenant isolation, private community leakage,
  public Reverb channel leaking post titles.
- Plan: stays a social network (owner, 2026-10-18); fix isolation, private communities and the public channel first; resolve the diverged branches.

### Analytics
- Critical: "insights" are generated from platform names only, with canned
  fallbacks stored as real insights; any team owner can toggle global feature flags.
- Missing: working connectors/pipelines, AI SQL, real KPIs, mounted dashboard builder.
- Plan: remove fabricated insights, restrict flags, merge branches, real inputs.

### Ehail
- Critical: any applicant can approve their own driver application.
- High: any user can list every ride and the platform revenue; accept path skips
  the observer so the passenger sees no update.
- Missing: payments, ratings, vehicle management, dispatch.
- Plan: fix approval, scope rides, observer on accept, then payments through Billing.

### Email
- Out of scope. No folder, spec, registry entry or reference ever existed; owner dropped it.

## 4. Dependency map and contracts

```
InfoDot (identity, companies, registry)
  ├─ signs redeem + membership webhooks ──► every platform (SSO, team sync)
  └─ search / registry APIs ◄── Files, Docs (today); others later

Notify  ◄── Billing (dunning), Tasks (due/escalation), HR (leave), Auction (outbid/won),
            Emall (order), Ehail (ride), Farms (harvest)      [one ingest API]
Billing ◄── Auction (settlement), Emall (checkout), Ehail (fare), Tutor (booking)
Finance ◄── Billing (invoices as source documents)            [later]
Memory/Brain/Dopemine: events only where the loop in ADR-0015/0017/0018 has a use;
  Dopemine stays narrowed to engagement interventions (client retention).
```

Contracts used for every cross-platform call:
- Signed server-to-server: HMAC-SHA256 over `{timestamp}.{raw body}` with the
  platform's secret, 5 minute tolerance (same as the membership webhook).
- Every outbound call is fire-and-forget behind a queue with a timeout and a
  circuit breaker; a Notify, Memory or Brain outage must never fail the user action.
- Each event carries an idempotency key; receivers store it with a unique index.
- Company id travels on every call; receivers verify membership, not the sender's claim.

## 5. Prioritised plan

1. **Foundations (before platform features):** SSO redeem conformity pattern,
   CI + deploy workflow template, branch hygiene (decide and merge the stranded feature branches),
   hosting and DNS (owner).
2. **Security and correctness defects that are live risks once hosted:** Ehail
   self-approval and ride privacy; Auction bid atomicity; Analytics global flags and
   fabricated insights; Pulse isolation; Plug and Design role checks; Tasks status
   validation.
3. **Shared services:** Notify ingest + reliability; Billing provider + webhooks +
   entitlements.
4. **Platform completion in order of dependency:** Tasks, Auction, Emall, HR, Finance,
   Farms, Design, Ehail, then Analytics, Tutor, Plug, Pulse. Payment work waits for the provider decision.
5. Deploy one platform at a time only when it has CI, a database, DNS, SSO and a
   live smoke check.

## 6. Owner decisions and credentials needed

Decisions made by the owner (2026-10-18):
- **Pulse `main` is canonical; `feature/ecosystem-sso` is archived.**
- **Dot.Email is dropped.** No code exists; treated as a mix-up with Ehail.
  Scope is 13 platforms.
- **Pulse stays a social network** for now (no business-publishing rebuild).
- **Plug stays an extension marketplace** (no integration-manager rebuild).
- **Tutor stays a booking marketplace** (no courses or assessment build).

Still open:
- **Finance:** personal ledger or company finance? (Changes tenancy.)
- **Payment provider** for Billing, Emall, Auction, Ehail (owner will decide later;
  payment work waits for it).

Credentials and infrastructure (owner-only):
- DNS records and cPanel subdomains for 7 unresolved names; database per platform.
- `ECOSYSTEM_SECRET_<KEY>` per platform in InfoDot and `INFODOT_SERVICE_SECRET` /
  `INFODOT_PLATFORM_KEY` in each satellite.
- SMTP, SMS and push provider keys for Notify; payment provider keys and webhook secret.
- Anthropic key where AI is real (Analytics, Tasks, Billing, Pulse, Notify).
- Confirm production `.env` values differ from the local ones.

## 7. Verified test baseline (origin/main, SQLite)

Fresh clone of `origin/main`, `composer install`, `phpunit` against in-memory SQLite,
run 2026-10-18. SQLite is not production (Postgres/MySQL), so locking and
database-specific behaviour is still Not Verified. Counts on `main` are lower than
the feature branches because unmerged work is not in `main`.

| Platform | Result on origin/main |
|---|---|
| Analytics | 571 tests, 564 passed, 7 skipped |
| Pulse | 112 tests, 105 passed, 7 skipped |
| Finance | 113 tests, 106 passed, 7 skipped |
| Emall | 133 tests, 126 passed, 7 skipped |
| Auction | 82 tests, 75 passed, 7 skipped |
| Billing | 92 tests, 85 passed, 7 skipped |
| Tasks | 41 tests, 41 passed (realtime, recurrence and smart views are not on main) |
| HR | 54 tests, 53 passed, 1 skipped |
| Plug | 70 tests, 63 passed, 7 skipped, **only with `--testsuite Feature`**: `phpunit.xml` lists `tests/Unit`, which does not exist in a fresh clone, so a plain `phpunit` run errors |
| Tutor | 96 tests, 89 passed, 7 skipped |
| Design | 92 tests, 85 passed, 7 skipped (the editor commit is unpushed, so not included) |
| Notify | 106 tests, 99 passed, 7 skipped (hardening, rules and schedules are not on main) |
| Farms | 73 tests, 66 passed, 7 skipped |
| Ehail | 84 tests, 77 passed, 7 skipped |

"7 skipped" recurs across repos; the cause is the shared Jetstream scaffold and has
not been investigated.

## 8. Progress log

- 2026-10-18: discovery complete. 14 audits (13 platforms plus the Ehail/Email search)
  and baseline test runs for all 14 repos. Nothing changed or deployed yet.
- 2026-10-18: owner decisions recorded (Email dropped; Pulse, Plug, Tutor keep their current purpose). Wave 1 fix agents started (Ehail, Auction, Analytics, Tasks, Plug+Design); PRs only.
- 2026-10-18 wave 1 (PRs only, none merged or deployed; each PR's suite re-run by me on SQLite):
  - Tasks [Dot.Tasks#3](https://github.com/sakhilebhayi/Dot.Tasks/pull/3): 101 tests pass. AI headers, status validation, priority order, team scope, indexes.
  - Auction [Dot.Auction#3](https://github.com/sakhilebhayi/Dot.Auction/pull/3): 123 tests, 116 pass, 7 skipped. Locked bid transaction, idempotent settlement. Lock proven only on a throwaway Postgres run by the agent.
  - Ehail [Dot.Ehail#3](https://github.com/sakhilebhayi/Dot.Ehail/pull/3): 169 tests, 162 pass, 7 skipped. Self-approval closed, ride privacy, accept broadcast.
  - Plug [Dot.Plug#3](https://github.com/sakhilebhayi/Dot.Plug/pull/3): 88 tests, 81 pass, 7 skipped. Install/publish role checks, SSO throttle.
  - Design [Dot.Design#4](https://github.com/sakhilebhayi/Dot.Design/pull/4): 121 tests, 114 pass, 7 skipped. Operator-only catalog writes, honest mock AI, page-number race.
  - Analytics [Dot.Analytics#12](https://github.com/sakhilebhayi/Dot.Analytics/pull/12) (base `feature/ecosystem-sso`): 642 tests, 635 pass, 7 skipped. No fabricated insights, operator-only flags, enforced gates, encrypted webhook secrets, hardened AI SQL.
  - Deploy-time actions for the owner: set `users.is_platform_operator` for operators (Ehail, Design, Analytics via `analytics:operator`); rotate any Analytics webhook secrets that were stored in plaintext; review Auction's duplicate-winner demotion migration.
- 2026-10-18 wave 2, signed InfoDot sign-in (redeem) ported from Dot.Files to 13 platforms (Pulse excluded until its canonical branch is chosen). PRs only; each suite re-run by me on SQLite; InfoDot side [InfoDot#32](https://github.com/sakhilebhayi/InfoDot/pull/32) lists the secrets. Nothing calls a real InfoDot (faked HTTP only):
  - Tasks [#4](https://github.com/sakhilebhayi/Dot.Tasks/pull/4) 147 pass; Auction [#4](https://github.com/sakhilebhayi/Dot.Auction/pull/4) 162 pass/7 skip; Ehail [#4](https://github.com/sakhilebhayi/Dot.Ehail/pull/4) 193/7; Plug [#4](https://github.com/sakhilebhayi/Dot.Plug/pull/4) 113/7; Design [#5](https://github.com/sakhilebhayi/Dot.Design/pull/5) 155/7; Analytics [#13](https://github.com/sakhilebhayi/Dot.Analytics/pull/13) 680/7.
  - Finance [#4](https://github.com/sakhilebhayi/Dot.Finance/pull/4) 137/7; Emall [#4](https://github.com/sakhilebhayi/Dot.Emall/pull/4) 157/7; Billing [#2](https://github.com/sakhilebhayi/Dot.Billing/pull/2) 129/7; HR [#2](https://github.com/sakhilebhayi/Dot.HR/pull/2) 96/1.
  - Tutor [#3](https://github.com/sakhilebhayi/Dot.Tutor/pull/3) 124/7; Notify [#2](https://github.com/sakhilebhayi/Dot.Notify/pull/2) 190/7; Farms [#2](https://github.com/sakhilebhayi/Dot.Farms/pull/2) 107/7.
  - Known limits: an InfoDot "viewer" maps to "editor" in every app (they only define admin and editor); that is a real privilege gap in team-scoped apps (Billing, HR, Notify, Farms, Plug, Analytics, Tasks) and needs a read-only role. Per-platform registries still list some old `infodot.app` hosts.
  - Found: Dot.Tasks has no `app/Providers` or `app/Actions` in git (registration, team creation and Jetstream roles unconfigured; no auth tests existed). Fix PR in progress.
  - Deploy order per platform: DNS + database, set `ECOSYSTEM_SECRET_<KEY>` in InfoDot and `INFODOT_SERVICE_SECRET` + `INFODOT_PLATFORM_KEY` in the app, mark the platform live in InfoDot's registry, run migrations, run `infodot:link-team --promote` for existing companies.
- 2026-10-18 wave 3 (PRs only; I re-ran the suites):
  - Tasks Fortify/Jetstream wiring [Dot.Tasks#5](https://github.com/sakhilebhayi/Dot.Tasks/pull/5): 158 pass. The 11 new tests failed 11/11 before (registration, login, team creation, roles all unconfigured). Check the production server before merging: files absent from git everywhere.
  - Notify ingest and reliability [Dot.Notify#3](https://github.com/sakhilebhayi/Dot.Notify/pull/3): 288 pass/7 skip (also green on a throwaway Postgres 16 run by the agent). Signed `POST /api/ingest/events`, queued retries, idempotency, opt-outs, encrypted secrets, SSRF guard, role policies. Needs a queue worker, a DB backup before the encryption migration, and `NOTIFY_INGEST_SECRET_<KEY>` per caller. Privacy/cookie text was edited (it claimed tracking pixels): owner to read.
  - CI workflows on GitHub Actions, one PR per repo, all `tests` checks green on GitHub: Tasks#6, Auction#5, Ehail#5, Notify#4, Farms#3, Finance#5, Emall#5, Billing#3, HR#3, Plug#5, Tutor#4, Design#6, Pulse#4 (Analytics already had CI).
  - In flight: Auction seller listings/anti-snipe/buy-now/images; Farms edit-delete/state machine/units/reports; HR self-service leave/audit trail/retention; Tutor onboarding/availability/lifecycle.
  - Waiting on owner: payment provider (Billing, Emall, Auction settlement, Ehail), Finance personal vs company, Pulse canonical branch, DNS/DB/secrets for hosting.
- 2026-10-18 wave 4 feature PRs (PR-only; I re-ran each suite, several times, and fixed two flaky tests the agents shipped: a short numeric `assertDontSee` sentinel collided with random page data in Tutor and Farms):
  - Tutor [#5](https://github.com/sakhilebhayi/Dot.Tutor/pull/5): 212 pass/7 skip. Tutor onboarding, subject catalogue, availability and non-overlap booking under lock, lifecycle enums, role-scoped KPIs, safe user deletion, rate limits.
  - Farms [#4](https://github.com/sakhilebhayi/Dot.Farms/pull/4): 332 pass/7 skip. Soft delete and edit everywhere, cycle state machine, units, fail-closed team scope, reports and CSV, demo seeder gated to local/testing. Backup before migrating (rewrites unit values).
  - Auction [#6](https://github.com/sakhilebhayi/Dot.Auction/pull/6) (257 pass/7 skip: seller listings, anti-snipe, buy-now, ending-soon notice, settlement hand-off row) then [#7](https://github.com/sakhilebhayi/Dot.Auction/pull/7) (297 pass/7 skip: private seller photos). Merge order #3, #4, #6, #7. No payment: winner is told payment is not available yet.
  - HR [#4](https://github.com/sakhilebhayi/Dot.HR/pull/4): 236 pass/1 skip, 8 repeat runs clean. Roles incl. a new Jetstream `viewer`, self-service leave with balances and overlap checks, decision integrity (no self-approval), append-only audit log, soft delete + purge (default 3 years, not legal advice), encrypted PII, fail-closed team scope. Backup first; needs a stable APP_KEY.
  - Observed, not fixed: the notification bell sends a Livewire call named `toJSON` on outside clicks (500 in the log) on the Auction base; other platforms not checked. AutoBidEngine bypasses anti-snipe and buy-now (still unused).
  - In flight (wave 5): Billing engine (money in cents, invoices, renewals, payment seam, entitlements, honest dunning), Finance ledger integrity (derived balances, transfers, audit, import dedupe, forecast labelled as projection), Emall order integrity (line snapshots, restock, seller privacy, images), Ehail operations (ratings, vehicles, ride expiry, PII encryption), Pulse isolation and fail-closed moderation (on `main`; canonical branch undecided), Tasks workflows (viewer role, labels, filters, archive, mentions, dependencies).
- 2026-10-18 wave 5 (PR-only; each branch of each stack re-run by me, 4 runs, 0 failures; agents also ran their final suites on Postgres 16):
  - Tasks workflows [#7](https://github.com/sakhilebhayi/Dot.Tasks/pull/7), [#8](https://github.com/sakhilebhayi/Dot.Tasks/pull/8), [#9](https://github.com/sakhilebhayi/Dot.Tasks/pull/9): 339 pass. Viewer role (maps InfoDot viewer correctly), labels, filters, My tasks, archive, mentions, dependencies, scheduler docs.
  - Pulse [#5](https://github.com/sakhilebhayi/Dot.Pulse/pull/5), [#6](https://github.com/sakhilebhayi/Dot.Pulse/pull/6), [#7](https://github.com/sakhilebhayi/Dot.Pulse/pull/7) on `main`: 248 pass/7 skip. Private communities, private channels, fail-closed queued moderation, reports, rate limits. Needs a queue worker. Conflicts with `feature/ecosystem-sso` in 38 files: treat as spec if that branch is chosen.
  - Ehail [#6](https://github.com/sakhilebhayi/Dot.Ehail/pull/6), [#7](https://github.com/sakhilebhayi/Dot.Ehail/pull/7), [#8](https://github.com/sakhilebhayi/Dot.Ehail/pull/8): 364 pass/7 skip. Ride lifecycle and expiry, cancellation/reassignment, ratings, vehicles, encrypted PII, operator desk. Needs the scheduler cron; back up before each.
  - Emall [#6](https://github.com/sakhilebhayi/Dot.Emall/pull/6), [#7](https://github.com/sakhilebhayi/Dot.Emall/pull/7), [#8](https://github.com/sakhilebhayi/Dot.Emall/pull/8): 336 pass/7 skip. Order snapshots, cancellation and restock, seller privacy, idempotent checkout, verified reviews, photos, public browsing. Back up before #6; storage:link and gd for #8.
  - Billing [#4](https://github.com/sakhilebhayi/Dot.Billing/pull/4), [#5](https://github.com/sakhilebhayi/Dot.Billing/pull/5), [#6](https://github.com/sakhilebhayi/Dot.Billing/pull/6): 369 pass/10 skip (SQLite). Money in cents, plans, invoice engine, payment-recording seam (no provider), signed entitlements API, honest dunning, Notify client. Owner must confirm the billing model and erasure policy.
  - Finance [#6](https://github.com/sakhilebhayi/Dot.Finance/pull/6), [#7](https://github.com/sakhilebhayi/Dot.Finance/pull/7), [#8](https://github.com/sakhilebhayi/Dot.Finance/pull/8): 386 pass/7 skip. Derived balances, cents, per-currency totals, transfers, audit, soft delete, edit screens, import dedupe, cash flow, forecast labelled as projection. Run migrations in maintenance mode; opening balance is dated the migration day.
  - Still not built: Analytics real-data pipeline (insights fixed to be honest, connectors/pipelines still unreachable); Design asset uploads and real AI provider; calls from the apps to Notify (Notify is ready; Billing has a client); read-only viewer role in Plug, Notify, Farms, Analytics (Tasks, HR and Billing have it); Pulse SSO (blocked on the canonical branch).
- 2026-10-18 owner decision: Pulse `main` is canonical, `feature/ecosystem-sso` archived. In flight: Pulse signed sign-in (stacked on Pulse #5-#7) and a read-only `viewer` role for Plug, Notify, Farms, Analytics (Tasks, HR, Billing already have it).
- 2026-10-18 wave 6: Pulse signed sign-in [Dot.Pulse#8](https://github.com/sakhilebhayi/Dot.Pulse/pull/8) (290 pass/7 skip); read-only `viewer` role in Plug [#6](https://github.com/sakhilebhayi/Dot.Plug/pull/6) (134/7), Notify [#5](https://github.com/sakhilebhayi/Dot.Notify/pull/5) (313/7), Farms [#5](https://github.com/sakhilebhayi/Dot.Farms/pull/5) (395/7), Analytics [#14](https://github.com/sakhilebhayi/Dot.Analytics/pull/14) (752/7); unknown InfoDot roles now fall back to viewer, not editor, in those four. Closed two ungated AI actions in Notify and three ungated actions in Analytics. Merge order and deploy prerequisites: see `docs/platform-merge-and-deploy-runbook.md`.

