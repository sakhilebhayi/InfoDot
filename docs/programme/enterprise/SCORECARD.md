# InfoDot readiness scorecard (2026-10-30)

Scored with [READINESS_MODEL.md](READINESS_MODEL.md). "Baseline" is `main` at `c89286f`. "After PR #161" is the branch, **not merged and not deployed**: it earns points only for what exists in the repository and CI, and none of the production-side effects. Each row names its evidence. I scored conservatively where a criterion needs something I have not seen run.

## Result

| | Baseline | After PR #161 |
|---|---|---|
| Total (100) | **36** | **66** |
| Release blockers open | 3 (payment races, account takeover from a satellite, untested restore) | **1** (untested restore, C-10) once deployed; 3 until then |
| Lowest dimension against the 7/10 floor | Performance 0/8, Reliability 1/9 | Performance 2/8, Reliability 6/9 |
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
| | Named failure tests for payments (4) | 2 | 3 | Duplicate webhooks, refunds existed; added double refund, double checkout, sweep race. No concurrent-seat test on MySQL |
| API (8) | Contracts documented with schemas (3) | 1 | 2 | `docs/api/openapi.yaml`, 8 paths; the candidates response is not fully described |
| | Idempotency/replay tests per inbound webhook (3) | 2 | 2 | Ticket events and redeem covered; LiveKit webhook less so |
| | Outbound calls have timeouts and visible failure (2) | 1 | 1 | Timeouts yes; parked outbox rows raise no alert (C-17) |
| Performance (8) | Measured response times at a stated data size (4) | 0 | 0 | Not measured |
| | No unbounded query in request paths (2) | 0 | 1 | Two main lists bounded; analytics and search remain |
| | Indexes support the queries (2) | 0 | 1 | Hot-path indexes added; others remain (C-24) |
| Reliability (9) | Error tracking and alerts reach a person (3) | 0 | 1 | Sentry wired; DSN and alert routing unverified |
| | Health check (2) | 1 | 2 | `/up` plus `/ready` |
| | Queue/schedule failures visible and retried safely (2) | 0 | 2 | `ops:check` reports parked/stale outbox rows, unconfirmed refunds and unreleased holds; `/ready` shows a stale scheduler; locks expire in 30 minutes; refunds are marked before Billing is called. Not yet proven on production (needs a monitor on `/ready`) |
| | Structured logs, request id, no personal data (2) | 0 | 1 | Request id on every log line, Sentry event and response; daily rotation. Logs are plain text, not JSON |
| DevOps (8) | Required checks on the default branch (2) | 0 | 0 | P-01 |
| | Release gate runs the same checks (2) | 1 | 2 | `deploy.yml` calls `ci.yml` |
| | Migration check on MySQL (1) | 1 | 1 | |
| | Post-deploy smoke test (1) | 1 | 1 | |
| | Documented, drilled rollback (2) | 0 | 0 | Rollback copy exists; never drilled |
| Privacy (8) | Export and deletion implemented and tested (3) | 3 | 3 | PRs #156 and #158 |
| | Retention schedule enforced by a job (2) | 1 | 1 | `seminars:prune` covers seminar data only |
| | Policy text matches code, adviser-reviewed (2) | 0 | 0 | Drafts reconciled; only the host agreement approved; policy and terms not live |
| | Incident procedure and processor list (1) | 0 | 0 | |
| Business and UX (6) | Journeys as automated or recorded end-to-end tests (4) | 1 | 1 | Feature tests and a puppeteer room test exist; not whole journeys |
| | Accessibility and mobile checks recorded (2) | 0 | 0 | |
| **Total (100)** | | **36** | **66** | |

## What would move the score most (largest gap first)

1. **Performance (6 points open).** A staging copy with a seeded database and a load script for the core pages and the seminar endpoints. Without this, no claim about capacity is possible.
2. **Reliability (3 open).** A Sentry DSN and an alert that reaches a person, and an uptime monitor on `/ready` (both Owner); JSON logs.
3. **DevOps (4 open).** Branch protection with required checks (Owner decision); a drilled rollback and a tested restore (C-10, which is also the last release blocker).
4. **Tenancy (6 open).** Tests for exports, cache keys and the LiveKit token (A-05).
5. **Privacy (4 open).** Adviser review of the policy and terms, a processor list, an incident procedure, and retention for the rest of the data.
6. **Business and UX (5 open).** Whole-journey end-to-end tests and accessibility checks.

Reaching 90 needs most of these; it is not a matter of one more PR.
