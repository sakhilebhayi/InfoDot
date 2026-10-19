# Remediation roadmap (baseline 2026-10-19)

Ordered by severity, blast radius and dependency. Each item names its acceptance test.

## R-1 Scheduler staleness after the 13 deploys (reliability, high)

Evidence (verified, GitHub Actions "Scheduler Watchdog" history, runs before vs after 2026-10-18 16:00 UTC, the
time the 13 platforms were adopted): failed runs rose from 0 of 26-41 before to 9/59 (Central), 13/74 (Engage),
19/71 (Forms), 19/74 (Press), 24/74 (Sheet), 21/67 (Agents), 7/66 (Projects), 7/75 (Dopemine), 4/68 (Mines),
2/74 (Memory); Charts 0/75. Example probe reply: scheduler age 257 s against a 180 s limit. Before the deploys
the same watchdogs failed essentially never.

**Root cause (verified 2026-10-19, Host Health run 39274368839).** The crontab has 45 lines, 43 of them
`* * * * *`, all starting in the same second. A burst sample at the minute boundary showed 57 `artisan` processes
alive 7 s in (cron's `sh -c` parents were visible in the first run's process list), and the host answered `bash: fork: retry: Resource temporarily
unavailable`: the account's process limit is hit. A cron job or SSH session that cannot fork starts late or not at
all, which explains the stale scheduler probes, the SSH exit 255 / "exec request failed" drops during deploys and
the occasional 503/timeout. Host load average was about 6 on 32 cores and `artisan --version` boots in about
0.3 s, so CPU is not the constraint; process count is. The account's exact process limit is not readable from
inside the shell (`ulimit -u` shows the kernel maximum, the limit is applied by the host).

**Fix (PRs open, nothing deployed).** In the 13 deploy pipelines that were added on 2026-10-18 (Dot.Tasks #18,
Auction #19, Ehail #18, Notify #15, Farms #16, Finance #18, Emall #17, Billing #15, HR #13, Tutor #12, Design #15,
Pulse #14, Analytics #24): `exec` before `php` (one process per line instead of two) and the queue worker on alternate
minutes by marker parity. Estimate, not measured: lines starting in one minute drop from 43 to about 36 and each line is one process instead of two, so the peak falls from roughly 100 processes to roughly 36. Takes effect per app on its next
deploy. The 20 older cron lines (Mines, Agents, Central, Press, Forms, Engage, Sheet, Projects, Memory, Dopemine,
docs, Charts) are unchanged and are the next step if the failure rate stays above target.

**Result of the first fix (verified 2026-10-19 16:57 UTC, Host Health run 39289886705, after all 13 deploys).**
Crontab lines that fire every minute fell from 43 to 31 (12 queue workers now run every second minute).
The burst sample still shows 51 `artisan` processes alive 7 s after the minute boundary and the sampler itself
still gets `bash: fork: retry: Resource temporarily unavailable`. **The peak was reduced, not removed; R-1 is
not resolved.** The Scheduler Watchdog effect cannot be judged yet (deploys finished minutes ago); compare the
failure rate again after 24 to 48 h.

Why it is not enough: about 20 of the remaining lines belong to the older apps (unchanged, still two processes
per line) and every line still starts in the same second, so the peak is set by how many start together, not by
how long each runs.

**Next step (proposed, not built): a bounded-concurrency runner.** One crontab line per minute starts a small
script that runs every app's `schedule:run` first and the queue workers after, at most 4 at a time
(`xargs -P 4`), reading per-app command files from `~/.dot-cron.d/`. Peak processes become about 10 whatever the
number of apps, and a slow app cannot starve the others. Needs: the runner on the host, each pipeline writing its
command file instead of crontab lines (26 repositories, two templates), a one-time migration of the existing
lines, and the same read-back verification the crontab writer already uses.

**Acceptance (not yet met).** Burst sample without `fork` errors; Scheduler Watchdog failure rate under 2% over
48 h with every platform's scheduler still running.

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
