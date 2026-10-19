# Remediation roadmap (baseline 2026-10-19)

Ordered by severity, blast radius and dependency. Each item names its acceptance test.

## R-1 Scheduler staleness after the 13 deploys (reliability, high)

Evidence (verified, GitHub Actions "Scheduler Watchdog" history, runs before vs after 2026-10-18 16:00 UTC, the
time the 13 platforms were adopted): failed runs rose from 0 of 26-41 before to 9/59 (Central), 13/74 (Engage),
19/71 (Forms), 19/74 (Press), 24/74 (Sheet), 21/67 (Agents), 7/66 (Projects), 7/75 (Dopemine), 4/68 (Mines),
2/74 (Memory); Charts 0/75. Example probe reply: scheduler age 257 s against a 180 s limit. Before the deploys
the same watchdogs failed essentially never.

Likely cause (hypothesis, **not yet proven**): each new app adds a `schedule:run` line and a `queue:work` line to
the one shared crontab, all firing every minute on a shared account with process limits; the SSH drops (exit 255,
"exec request failed") and the odd 503 seen during deploys point the same way.

Plan: (1) read the host's limits and cron timing through an existing `server-inspect`/`server-logs` workflow or
the owner; (2) combine the two lines per app into one boot, stagger start minutes, and run `queue:work` only when
the queue has work or at a lower frequency for apps whose schedule is daily; (3) re-measure the watchdog
failure rate over 48 h. Acceptance: watchdog failure rate back to the pre-18-Oct level (under 2%) with all 13
platforms still running their schedules.

## R-2 Dependency advisories (security, high for Pulse and axios, medium otherwise)

Update `guzzlehttp/guzzle`, `livewire/livewire`, `laravel/framework`, `league/flysystem`, `symfony/*` and `axios`
in the 17 affected repositories, one PR each, suite green, then deploy in the usual one-at-a-time order. Add
`composer audit` and `npm audit` as a CI step so new advisories fail a PR. Acceptance: `composer audit --locked`
reports none on every default branch.

## R-3 Backups and restore test (operations, high)

No backup inventory exists for the 13 SQLite databases or the older apps. Define what is backed up (database files,
`.env`, uploads), where (encrypted, off-host), how often, and perform one real restore into a scratch location.
Acceptance: a written RPO/RTO from a measured restore.

## R-4 Finish Analytics wiring

Owner step pending: `ANALYTICS_INGEST_URL` on the 11 publishing apps; then verify the first signed snapshot is
accepted and shown. Acceptance: one visible metric from each publisher.

## R-5 Real sign-in and workflow verification (product, high)

Browser end-to-end run from InfoDot into each of the 13 platforms, then each platform's primary workflow
(including one Paystack test payment in Billing, Emall, Auction, Ehail). Acceptance: recorded pass per platform.

## R-6 Benchmark and scorecard acceptance criteria

Inspect Dot.Mines against section 3 of the brief and write the measurable criteria; then score every platform.

## R-7 Security review of the older platforms

Tenant isolation tests, MFA, rate limiting, headers, uploads, operator boundaries, secret scanning of history.

## R-8 Scale model

Write workload scenarios (registered vs active vs concurrent), then measure: seeded datasets and a load run
against a non-production copy. The shared-host architecture (one account, SQLite, no daemons, no Reverb) cannot
be assumed to reach the one-million-registered-user goal; the plan must say what changes (managed database,
dedicated queue workers, object storage) and be validated by the load test, not asserted.

## Later phases

Design system, integration contracts, Brain/Memory/Dopemine governance, compliance matrix (POPIA, GDPR), disaster
recovery drills, investor due-diligence pack: each written when its evidence exists.
