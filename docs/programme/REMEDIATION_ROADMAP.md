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

**Runner pilot (verified 2026-10-19 17:23 to 17:47 UTC; tasks, notify and pulse migrated, 39 other crontab lines unchanged).**
The runner works as designed (3 schedule + 3 queue commands per pass, never more than 4 at once) but the pilot
shows the real constraint. In 9 minutes of passes, a scheduler command was killed in most minutes: exit code 137
(SIGKILL) for notify, tasks and pulse, 11 to 18 s after the minute boundary, and twice exit 125 (`timeout` itself
could not fork). Passes took 22 to 26 s for six commands (the slowest single `schedule:run`: 15 s). The same
SIGKILL (137) killed `composer install` during the Tasks deploy that left that site in maintenance mode. So the
host does not only refuse forks: it kills our PHP processes when the account is over a limit, and the remaining
39 per-minute crontab lines still start together at second 0 right next to the runner. Hypothesis, **not yet
confirmed**: a memory or entry-process limit on the account; cPanel's Resource Usage page (CloudLinux) would show
the limit and the fault counts and is the quickest way to settle it.

Consequences: migrating a few apps does not help while the rest still burst at second 0; the runner must carry
every app (the 20 older cron lines need their pipelines changed to command files too); and the runner itself
should start away from second 0. Diagnostics (PR #46: exit codes, slowest commands, failure log tails) is what
exposed this.

**Results of the rollout so far (verified from the runner's `failures.log`, 2026-10-19 UTC).** Failures are
commands that exited non-zero (137 = killed, 125 = `timeout` could not fork) and whose own log was readable, so the
counts are a lower bound.

| Period | Setup | Failures | Pass length |
|---|---|---|---|
| 17:40 to 17:51 (11 min) | runner for 3 apps; 39 other crontab lines at second 0 | 13 | 22 to 26 s |
| 17:52 to 18:05 (14 min) | runner for all 13 new apps at second 0; 20 older lines at second 0 | 1 (billing, 17:56) | 29 to 34 s |
| 18:06 to 18:16 (10 min) | same, runner started at second 30 | 0 | 18 to 19 s |
| 18:36 to 18:46 (10 min) | batch 1 of the older apps moved (memory, dopemine, docs, projects, charts, central; mines moved by its own deploy) | 0 | 46 to 48 s |
| 18:46 to 18:55 (9 min) | batch 2 moved (agents, press, forms, engage, sheet): the crontab holds only the runner line and the Charts analytics keepalive | 0 | 49 s |

Single `schedule:run` commands still take up to 10 s each, which says the account is CPU or memory constrained
and not only process constrained. The cause is still a hypothesis until the host's Resource Usage page is read.

**Next (PRs open, nothing merged).** The 12 older pipelines get the same runner mode and `exec`: Mines #427,
Agents #12, Central #57, Press #44, Forms #39, Engage #42, Sheet #50, Projects #17, Memory #25, Dopemine #21,
docs #84, Charts #17. Each passed the real-block test on a fake host (13/13 checks): the actual cron section of the
file, with and without the runner. After merging, each app migrates itself on its next deploy, or via the Cron Runner
workflow. **Done 2026-10-19: that target is reached** (25 `schedule:run` and 18 `queue:work` commands run through the runner).

**Acceptance (not yet met).** Burst sample without `fork` errors; Scheduler Watchdog failure rate under 2% over
48 h with every platform's scheduler still running.

## R-2 Dependency advisories (security, high for Pulse, Charts and axios, medium otherwise)

Status 2026-10-19 (nothing merged or deployed): one PR per repository updates only the advised packages and their
dependencies (`composer.json` unchanged), `composer audit --locked` clean afterwards, CI green on all of them:
Pulse #16 (the high Guzzle advisory), Auction #21, Billing #17, Design #17, Dopemine #22, Ehail #20, Emall #19,
Farms #18, Files #97, Finance #20, HR #15, Memory #26, Notify #17, Plug #12, Projects #18, Tasks #20, Tutor #14
(all `Dot.<Name>`); axios 1.18.1 to 1.20.0 in Analytics #26 and Files #96 (`npm audit` 0, build passes).

**Correction to the baseline:** Dot.Charts keeps its Laravel app in `backend/`, so the first audit skipped it. Its
backend has **48 advisories across 15 packages, 14 of them high** (Guzzle, league/commonmark, symfony/mime,
symfony/http-kernel, laravel/framework, phpunit). PR Charts #18 updates it (PHP 8.2 floor of its composer.json
kept; Laravel 12.48.1 to 12.70.0), audit clean afterwards, backend suite 239 of 240 locally (the one error needs a
gitignored signing key and fails identically on the old lock). **Dot.Charts, Dot.Memory and Dot.Dopemine have no test
CI**, so for those three the suite must be run by hand before deploying. Charts' `frontend/` lockfile is not audited
yet.

Prevention: the weekly **Dependency Audit** workflow (InfoDot PR #52, `tools/audit-dependencies.sh`) audits every
repository's default-branch lockfiles (Charts: `backend/`) and fails on any advisory so GitHub emails the owner. It
reports until the PRs above are merged.

Rollout: merge the PRs, then deploy one app at a time as usual (Pulse and Charts first). Acceptance: the Dependency
Audit run reports no advisories.

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

### R-1 status (2026-10-19 18:55 UTC)

All 25 apps run through the runner; 0 command failures logged in the 19 minutes since the last batch moved, against
13 in 11 minutes before the rollout started. One caveat for the next change: a pass now takes about 49 s, started at
second 30, so it ends around second 19 of the next minute. Another 10 to 15 s of work would make passes overlap
(the schedule-pass lock then skips a minute and writes `last-skip.log`). Mines' `schedule:run` alone takes 20 to 23 s.
The older apps' Scheduler Watchdog runs since 18:30 all succeeded, but only 1 to 2 runs per app are in that window
(GitHub's scheduler was degraded), so the 48 h comparison against the pre-18-Oct baseline is still outstanding.
R-1 stays open until that comparison and the cPanel Resource Usage reading are in. The process-limit hypothesis is
unconfirmed.
