# InfoDot enterprise-readiness model (method, written before any score is given)

Written 2026-10-30 as Phase 0 of the enterprise programme. **No score is assigned in this file.** It defines how a score will be earned so the same method scores the baseline and the final state.

## Rules

1. A score for a dimension is the sum of its **criteria**; each criterion is worth points only when its **evidence** exists in the repository, CI or a recorded test. "The feature exists in code" earns nothing by itself.
2. Evidence is one of: a CI check that passes and fails when it should; an automated test named in the register; a recorded measurement (tool, command, date, result) in `docs/programme/enterprise/evidence/`; a restore or failure drill with a dated record. A claim in a README is not evidence.
3. **Release blockers** override the average. If any is open, the platform is "not ready" whatever it scores:
   - a known critical (or high, without an approved written mitigation) vulnerability;
   - a confirmed cross-tenant or cross-user data leak;
   - a payment, refund or seat-licence path that can double-charge, double-refund or exceed a limit, or that is untested;
   - a data-loss risk with no tested restore.
4. Not applicable criteria are removed from the denominator with a written reason, never scored as passed.
5. A 9+/10 overall needs: no release blocker, every dimension at least 7/10, and the weighted total at least 90/100.

## Dimensions, weights and criteria (100 points)

| Dimension | Weight | Criteria (points) and the evidence that earns them |
|---|---|---|
| Security | 20 | No high/critical dependency advisory, enforced in CI (4). Access control: every state-changing route has a server-side check and a test that fails without it (6). Input handling: no unreviewed raw output of user content, no raw SQL from input, fail-closed webhooks (4). Secrets: none in the repository, scanning on (2). Rate limiting on auth and expensive routes (2). Security headers and session settings reviewed (2). |
| Tenant isolation and authorisation | 15 | A cross-team / cross-user test for each class of tenant data: company-only content, seminar attendee and payment data, seats, exports, cache keys (9). Role matrix tested including revoked and removed members (4). Lifecycle (invite, remove, close) leaves the person's own identity intact, tested (2). |
| Code quality | 8 | PHPStan at the agreed level with a baseline that only shrinks, enforced in CI (4). Pint clean, enforced in CI (2). Domain boundaries for money, seats and reputation reviewed and documented (2). |
| Testing | 10 | All tests pass in CI (2). Measured line coverage of `app/` published by CI; points scale with the measured figure (4: 40% earns 1, 60% earns 2, 70% earns 3, 80% earns 4). Named failure tests for payments (duplicate webhook, timeout, concurrent seats, retries) (4). |
| API and integrations | 8 | Server-to-server contracts documented with schemas and signing (3). Idempotency and replay tests for each inbound webhook (3). Outbound calls have timeouts, retries and a failure state a person can see (2). |
| Performance | 8 | Measured response times of the core pages and endpoints against a seeded database at a stated size (4). No unbounded query on a growing table in a request path (2). Indexes support the queries the code makes (2). |
| Reliability and observability | 9 | Error tracking and alerts that reach a person (3). Health check covering database, cache, queue (2). Queue and schedule failures are visible and retried safely (2). Structured logs with a request id and no personal data (2). |
| DevOps | 8 | Merge gate: required checks on the default branch (2). Release gate runs the same checks (2). Migration safety check on MySQL (1). Post-deploy smoke test (1). Documented, drilled rollback (2). |
| Privacy and compliance | 8 | Export and deletion implemented and tested (3). Retention schedule enforced by a job (2). Privacy and terms text matches the code (reviewed by the adviser) (2). Incident procedure and processor list documented (1). |
| Business functionality and user experience | 6 | The journeys in Phase 11 pass as automated or recorded end-to-end tests (4). Accessibility and mobile checks recorded (2). |

## What a baseline score needs

For each criterion the baseline report states the evidence found or "none", so a score can be added up by anyone. Phase 12 repeats exactly this table.
