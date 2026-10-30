# InfoDot readiness scorecard (2026-10-30)

Scored with [READINESS_MODEL.md](READINESS_MODEL.md). "Baseline" is `main` at `c89286f` (2026-10-30, before the programme). "After" is the programme's work through PR #168: #161 to #167 are merged and deployed; **#168 is not merged and not deployed**, and earns points only for what exists in the repository and CI. Each row names its evidence. I scored conservatively where a criterion needs something I have not seen run.

## Result

| | Baseline | After (through #168) |
|---|---|---|
| Total (100) | **36** | **78** |
| Release blockers open | 3 (payment races, account takeover from a satellite, untested restore) | **1** (untested restore, C-10) |
| Lowest dimension against the 7/10 floor | Performance 0/8, Reliability 1/9 | Performance 4/8, Privacy 5/8 |
| 9+/10 reached | No | **No.** Needs 90+, every dimension at 7/10 or better, no blocker |

## By criterion

| Dimension (weight) | Criterion (points) | Baseline | After | Evidence |
|---|---|---|---|---|
| Security (20) | No high/critical advisory, enforced in CI (4) | 2 | 4 | `composer audit` 0 and `npm audit` 0; `tools/ci-composer-audit.sh` and `npm audit --audit-level=high` in `ci.yml`. Baseline: none present at high, but not a gate |
| | Access control tested per state-changing route (6) | 3 | 4 | Regression tests for each fix; not every route has a test that fails without its check |
| | Input handling (4) | 3 | 4 | Reviewer found no XSS/SQLi/SSRF; size limits added; webhooks fail closed. Baseline lacked size limits and had a GET login |
| | Secrets (2) | 1 | 2 | Scanning on; the committed `APP_KEY` was checked on the host and is not the live key (B-01); Pusher credentials in the same file not yet compared |
| | Rate limiting (2) | 1 | 2 | Auth forms, per-route buckets and the Livewire header search fan-out (B-07) |
| | Headers and session (2) | 0 | 1 | Headers added; no CSP; secure cookie set by env only |
| Tenancy (15) | Cross-team test per class of tenant data (9) | 4 | 7 | Existing `TenantIsolationTest` (knowledge), the removed-member Livewire test, and `SeminarAccessMatrixTest` (seminar edit/cancel/refund, attendee and rating data, meeting link, receipts, invoices, debts, exports, moderation). Still no test for cache keys; the LiveKit token is short-lived but not revocable
| | Role matrix incl. revoked/removed (4) | 2 | 3 | Invitation cancel and personal-team tests added |
| | Lifecycle keeps personal identity (2) | 1 | 1 | Not fully tested |
| Code quality (8) | PHPStan baseline that only shrinks, in CI (4) | 1 | 4 | 154 legacy findings remain; `phpstan-baseline.max` and `tools/check-phpstan-baseline.sh` make the baseline unable to grow |
| | Pint in CI (2) | 0 | 2 | |
| | Domain boundaries reviewed and documented (2) | 1 | 1 | `docs/infodot-architecture-guidelines.md` exists; not re-reviewed |
| Testing (10) | All tests pass in CI (2) | 2 | 2 | |
| | Coverage published by CI (4) | 0 | 4 | 94.0% of lines |
| | Named failure tests for payments (4) | 2 | 4 | Duplicate webhooks, double refund, double checkout, sweep race, cut-off cancel, pause switch, and the MySQL concurrency job (seats, capacity, accept) in CI |
| API (8) | Contracts documented with schemas (3) | 1 | 2 | `docs/api/openapi.yaml`, 8 paths; the candidates response is not fully described |
| | Idempotency/replay tests per inbound webhook (3) | 2 | 3 | Ticket events (duplicate, mismatch), redeem single-use, and the outbox claim test; LiveKit webhook signature and body-hash tests |
| | Outbound calls have timeouts and visible failure (2) | 1 | 2 | Timeouts everywhere incl. SMTP; parked rows, stale backlogs and overdue appeals reach ops:check and Sentry; Billing reads back off |
| Performance (8) | Measured response times at a stated data size (4) | 0 | 0 | Not measured |
| | No unbounded query in request paths (2) | 0 | 2 | Lists bounded; analytics grouped; notices preloaded; searches remembered per person; query-budget tests fail on one-per-row loops |
| | Indexes support the queries (2) | 0 | 2 | Hot-path indexes (#161) plus associates/comments/objects (#168), verified up and rollback |
| Reliability (9) | Error tracking and alerts reach a person (3) | 0 | 1 | Sentry wired; DSN and alert routing unverified |
| | Health check (2) | 1 | 2 | `/up` plus `/ready` |
| | Queue/schedule failures visible and retried safely (2) | 0 | 2 | `ops:check` reports parked/stale outbox rows, unconfirmed refunds and unreleased holds; `/ready` shows a stale scheduler; locks expire in 30 minutes; refunds are marked before Billing is called. Not yet proven on production (needs a monitor on `/ready`) |
| | Structured logs, request id, no personal data (2) | 0 | 1 | Request id on every log line, Sentry event and response; daily rotation. Logs are plain text, not JSON |
| DevOps (8) | Required checks on the default branch (2) | 0 | 2 | Eleven CI jobs required on `main` since 2026-10-30, admins included, force pushes and deletion blocked (P-01); Dependabot security updates on (P-02) |
| | Release gate runs the same checks (2) | 1 | 2 | `deploy.yml` calls `ci.yml` |
| | Migration check on MySQL (1) | 1 | 1 | |
| | Post-deploy smoke test (1) | 1 | 1 | |
| | Documented, drilled rollback (2) | 0 | 0 | Rollback copy exists; never drilled |
| Privacy (8) | Export and deletion implemented and tested (3) | 3 | 3 | PRs #156 and #158 |
| | Retention schedule enforced by a job (2) | 1 | 1 | `seminars:prune` covers seminar data only |
| | Policy text matches code, adviser-reviewed (2) | 0 | 0 | Drafts reconciled; only the host agreement approved; policy and terms not live |
| | Incident procedure and processor list (1) | 0 | 1 | `policies/INCIDENT_RESPONSE.md` and `policies/VENDOR_REGISTER.md` exist. They are procedures: not yet rehearsed, and the vendor facts marked [OWNER] are unfilled. Counted because the criterion asks that they be documented |
| Business and UX (6) | Journeys as automated or recorded end-to-end tests (4) | 1 | 4 | CustomerJourneysTest: five whole journeys through the real routes, including the paid ticket from payout setup to receipt and data rights |
| | Accessibility and mobile checks recorded (2) | 0 | 1 | AccessibilityBaselineTest: lang, zoomable viewport, one h1, labels, alt text, no positive tabindex on 11 pages. Colour, focus order and screen readers still need a person |
| **Total (100)** | | **36** | **78** | |

## What would move the score most (largest gap first)

1. **Performance (4 points open).** Response times and throughput against a seeded staging copy. Queries are now bounded and budget-tested, but no number has been measured, so no capacity claim exists. **Owner: the staging copy.**
2. **DevOps (2 open).** A drilled code rollback and the recorded MySQL restore (Owner runs the drills in `policies/RECOVERY_DRILL.md`; the restore is also the last release blocker).
3. **Privacy (4 open).** Adviser review and publication of the policy and terms; the processor facts marked [OWNER] in the vendor register; retention for data of people who still have an account.
4. **Reliability (3 open).** A Sentry DSN and an alert that reaches a person, an uptime monitor on `/ready` (both Owner), and JSON logs turned on with a reader.
5. **Tenancy (6 open from 15).** The matrix covers seminars and knowledge; cache keys and the entitlement data could still use their own cross-company tests.

Reaching 90 needs most of these; it is not a matter of one more PR.
