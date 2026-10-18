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
- **Dot.Email is dropped.** No code exists; treated as a mix-up with Ehail.
  Scope is 13 platforms.
- **Pulse stays a social network** for now (no business-publishing rebuild).
- **Plug stays an extension marketplace** (no integration-manager rebuild).
- **Tutor stays a booking marketplace** (no courses or assessment build).

Still open:
- **Finance:** personal ledger or company finance? (Changes tenancy.)
- **Payment provider** for Billing, Emall, Auction, Ehail (owner will decide later;
  payment work waits for it).
- **Pulse branches:** which of `main` and `feature/ecosystem-sso` is canonical.

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
