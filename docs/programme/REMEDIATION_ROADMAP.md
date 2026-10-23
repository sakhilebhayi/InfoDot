# Remediation roadmap (baseline 2026-10-19)

Ordered by severity, blast radius and dependency. Each item names its acceptance test.

## Status at 2026-10-23

Statuses are **done**, **partly done**, **not started** or **owner-dependent**, and each is backed by the section below it.
"Merged" and "deployed" are different. "Deployed" here means a `deploy.yml` run whose "Deploy the code" step succeeded on a
commit containing the change, or a live check where one is named; three runs on 2026-10-23 (Finance, HR, Pulse) are red at the
final probe step after the code was in place (see S-2f).

| Item | Status | Next step |
|---|---|---|
| R-1 Scheduler staleness | **partly done**: the bounded-concurrency runner carries all 25 apps; the acceptance (watchdog failures under 2%) is met by 3 of the 11 apps that have a Scheduler Watchdog | Read the cPanel Resource Usage peak for a failing minute; read why the failed watchdog runs failed |
| R-2 Dependency advisories | **done** for the 2026-10-19 set; new npm advisories since then are cleared in 6 repositories, 1 PR open; the Dependency Audit workflow has never run | Run Dependency Audit once; merge Forms #45; decide the Vite major for Charts and the Tailwind 4 upgrade |
| R-3 Backups and restore test | **partly done, owner-dependent**: tooling merged, never run on the host; no off-host destination; RPO/RTO table empty | Owner chooses the off-host destination, then dispatch Backups `install`, `inventory`, `run`, `restore-test` |
| R-4 Analytics wiring | **owner-dependent**: not re-checked in this update; no change recorded since 2026-10-19 | Set `ANALYTICS_INGEST_URL` on the 11 publishing apps, verify one signed snapshot |
| R-5 Real sign-in and workflow verification | **partly done**: signed sign-in and POST launch on the platforms, legacy GET removed on all 25; no browser end-to-end run, no Paystack test payment | Owner click-through from InfoDot into one platform; then the per-platform workflow runs |
| R-6 Benchmark and scorecards | **not started** | Inspect Dot.Mines against section 3 of the brief |
| R-7 Security review of the older platforms | **partly done**: S-2b to S-2e findings handled or planned, registration closed, viewer role; tenant isolation, MFA, rate limits not assessed | Document-root move (S-2e); owner actions on the archives (S-2f) |
| R-8 Scale model | **not started** | Workload scenarios, then a load run |
| R-9 Community rewards | **partly done**: increments A to D delivered and deployed, E partly (Brain recommendations and Memory APIs yes; expertise areas and non-email notifications no); F (acceptance run) not done | Acceptance run against section 11; owner steps listed under R-9 |
| R-10 InfoDot landing page | **not started; stays LAST** | After everything else |
| R-11 Dot.Brain Brand Feeling Engine | **partly done**: foundation merged (2026-10-23); scoring engine in an open PR; analysers and the Claude Skill not started | Owner review and ratification of the draft profiles; merge or amend Dot.Brain #32 |

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

**Done 2026-10-19 evening: all PRs merged and the 19 apps deployed one at a time (Pulse and Charts first); every site 200 afterwards, and the Dependency Audit script reports no advisories in any of the 27 repositories.** One runner failure was logged during the deploy wave (Notify, exit 255 at 19:47Z, while its code was being replaced). Original status: one PR per repository updates only the advised packages and their
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

**Status (2026-10-23): done for the 2026-10-19 set; follow-ups open.** Verified from the PR lists and workflow history:
- New npm advisories (vite 8.2.x middleware and `fs.allow`, launch-editor, follow-redirects) turned the audits red after 2026-10-19.
  `npm audit fix` without `--force` (lockfile only; the PR bodies report "found 0 vulnerabilities" afterwards) is merged for Central
  #63, Engage #50, Press #49, Sheet #58 and docs #89; Dot.Charts #19 merged it for `frontend/` and `backend/` npm (frontend
  6 to 2 advisories, backend npm 11 to 0). **Forms #45 is still open.**
- Dot.Charts: the two advisories left in `frontend/` (vite 5.4 and esbuild, dev server only) need a semver-major bump to Vite 6.4.5
  or 7.x; the decision is pending and nothing was changed.
- The Dot.Tasks CI PR reports Tailwind 3 transitive build-tool advisories that only a Tailwind 4 upgrade clears. The owner's note
  that about five high advisories remain in most repositories for the same reason is **not verified here**: the Dependency Audit
  workflow has no runs in the repository's history (checked 2026-10-23), so no all-repository count exists after 2026-10-19.
- CI now exists for Dot.Dopemine (#32), Dot.Memory (#30) and Dot.Charts (#19): tests, a frontend build, audits and a quality gate.
  Charts' gate excludes one PHPUnit test that needs the gitignored signing key (group `owner-machine`, owner decision), so CI runs
  239 of 240 backend tests; it still runs in full on the owner machine. This replaces the earlier note that these three had no test CI.

## R-3 Backups and restore test (operations, high)

No backup inventory exists for the 13 SQLite databases or the older apps. Define what is backed up (database files,
`.env`, uploads), where (encrypted, off-host), how often, and perform one real restore into a scratch location.
Acceptance: a written RPO/RTO from a measured restore.

**Status (2026-10-23): tooling merged (InfoDot #70, 2026-10-22), never run; off-host destination pending owner
decision; first measured restore pending.** Verified 2026-10-23: the Backups workflow has no runs in the repository's
Actions history, so nothing exists on the host from it. Built and merged: `tools/backup-host.sh` (database via the online-backup API / `mysqldump` /
`pg_dump`, `.env`, `storage/app` uploads; one AES-256 archive per app with a sha256; retention newest 14 + one per
month for 6 months; verify, scratch-only restore, optional off-host hook), `tools/test-backup-host.sh` (fake host, in
CI) and the Backups workflow (modes install, inventory, run, verify, restore-test, schedule, unschedule). The design, formats, key
handling, restore procedure and the empty RPO/RTO table are in [BACKUPS.md](BACKUPS.md). Not done: no workflow has been
run, nothing exists on the host yet, the off-host destination (S3-compatible, SFTP or pull) is the owner's choice, and
no restore has been timed, so the acceptance (a written RPO/RTO from a measured restore) is not met.

## R-4 Finish Analytics wiring

**Status (2026-10-23): owner-dependent; not re-checked in this update, no change recorded since 2026-10-19.** Owner step pending: `ANALYTICS_INGEST_URL` on the 11 publishing apps; then verify the first signed snapshot is
accepted and shown. Acceptance: one visible metric from each publisher.

## R-5 Real sign-in and workflow verification (product, high)

Browser end-to-end run from InfoDot into each of the 13 platforms, then each platform's primary workflow
(including one Paystack test payment in Billing, Emall, Auction, Ehail). Acceptance: recorded pass per platform.

**Status (2026-10-23): partly done.** Done (PR and deploy history, plus a live check on 2026-10-23):
- Signed InfoDot sign-in (the receiver asks InfoDot to prove the handoff token) is ported to Agents (#15), Central (#60), Engage (#45),
  Forms (#42), Press (#47), Projects (#21) and Sheet (#53) and deployed; InfoDot's side registry already listed them, and InfoDot
  #62 and #63 added the secrets tooling for Files, docs, Press and Sheet.
- InfoDot opens a platform through its `/go/{platform}` page, which POSTs a 60-second single-use token (S-2b). The legacy
  `GET /auth/ecosystem?token=` path is removed in all 25 receivers and deployed: a GET with a dummy token answers 302 to `/login` on
  every one of the 25 hosts (`curl -I`, 2026-10-23); the code change reads the token from the POST body only.
Not done: a recorded browser end-to-end run into each platform and each primary workflow; the Paystack test payments; the full
signed-in click-through from InfoDot remains an owner step (S-2b).

## R-6 Benchmark and scorecard acceptance criteria

Inspect Dot.Mines against section 3 of the brief and write the measurable criteria; then score every platform.

## R-7 Security review of the older platforms

Tenant isolation tests, MFA, rate limiting, headers, uploads, operator boundaries, secret scanning of history.

**Status (2026-10-23): partly done.** Findings and fixes so far are S-2b to S-2f in [SECURITY_ASSESSMENT.md](SECURITY_ASSESSMENT.md).
Also done: public registration is closed on the 25 platform apps (`ALLOW_REGISTRATION` default false, `/register` redirects to
`/login`; Dopemine first, then Memory, then the rest; live status in the execution log); a real read-only `viewer` role was added
to Agents (#16), Engage (#46), Press (#48), Projects (#22) and Sheet (#54), and Agents maps InfoDot companies to organisations
(#17); Forms already had a viewer role and Central maps InfoDot's viewer to its read-only manager role. Not done: tenant-isolation
tests on the older platforms, MFA, rate limiting, headers and CSP, secret scanning of history (S-3 is unchanged).

## R-8 Scale model

Write workload scenarios (registered vs active vs concurrent), then measure: seeded datasets and a load run
against a non-production copy. The shared-host architecture (one account, SQLite, no daemons, no Reverb) cannot
be assumed to reach the one-million-registered-user goal; the plan must say what changes (managed database,
dedicated queue workers, object storage) and be validated by the load test, not asserted.

## R-9 InfoDot community contributions and knowledge rewards (new requirement, 2026-10-19; partly done)

Owner brief stored verbatim in [requirements/infodot-community-rewards.md](requirements/infodot-community-rewards.md):
reward useful questions, answers, verified solutions, tutorials and corrections on InfoDot through the existing Dot
Points scoring and ledger in Dot.Brain / Dot.Memory / Dot.Dopemine, shown on the existing profile, with configurable
rules, quality gates, anti-abuse, an auditable lifecycle with reversals, and validated `infodot.*` events.

First steps (done 2026-10-20; the inspection and gap analysis are in [requirements/](requirements/)): (1) inspect what exists: InfoDot's question/answer/moderation/profile code (the
"Questions" page is in the signed-in UI) and the current Dot Points scoring, ledger and event contracts in the three
intelligence services; (2) write the gap analysis and the event contract (`infodot.question.published` ... 
`infodot.contribution.reward_reversed`) before any code; (3) build the rule engine and ledger integration first,
then moderation and anti-abuse, then the Brain / Memory / Dopemine integrations and the profile display. Depends on
R-5 (verified sign-in) and on the tenant-isolation review (R-7).

**Status (2026-10-23): increments A to D delivered; E partly delivered (see "Not done"); F (acceptance run) not done.** Design and increment letters:
[requirements/infodot-community-rewards-design.md](requirements/infodot-community-rewards-design.md). Merged and deployed (each
deploy is a green run on a commit containing the PR; the Dot.Brain workflow is dry-run only, below):
- **Dot.Dopemine** hosts the ledger: Dot Points ledger, signed intake and summary API (#25); back-office and expiry-warning
  delivery (#26); redemption API (#27); an InfoDot pull API for expiry warnings (#29); every InfoDot event type paid, with gated
  rewards unlocked on moderator or admin approval (#31); a signed weekly or monthly points digest API, **off by default** (#34).
- **InfoDot**: Q&A foundations, signed forwarding and profile (#59); moderation queue, appeals, audit trail and solution
  verification (#64); the Dot Points screen with rewards and redemption (#66); expiry-warning emails (#67); contribution
  achievements with opt-in sharing (#68); forwarding of verified, flagged and approved events and the moderator approval desk
  (#69); topics, topic browsing and opt-in answer suggestions (#72); opt-in points digest emails and Memory participation sharing
  (#74); the Verified library, the candidates API for Dot.Brain and the moderators' Recommended tab (#76, documented in
  [VERIFIED_LIBRARY.md](VERIFIED_LIBRARY.md)).
- **Dot.Memory**: the opt-in participation API (person-level topic and count, consent, purge, retention; #32, consent gaps closed in #33)
  and the knowledge-recommendations API with signed per-consumer endpoints (#35).
- **Dot.Brain**: `services/infodot-knowledge` (#29, ADR-0020), a rules-only recommender (no LLM) that pulls candidates, scores them
  and writes recommendations to Memory as decisions. Its workflow is **manual and dry-run by default, with no schedule**. The first
  dry run (2026-10-23 19:05 UTC, run 40008623813) pulled 1 candidate; verdict `do_not_recommend`; 0 decisions written.

**Not done:** the acceptance run against section 11 of the brief (F); expertise areas beyond topics; notifications beyond email; a
Brain run that writes decisions (none has been written) and a Brain schedule. **Owner steps:** check the `MAIL_*` configuration
on InfoDot (the expiry and digest emails depend on it), run the achievements backfill, and test the Memory participation sharing
switch end to end. The digest API stays off until the owner turns it on.

## R-10 InfoDot landing page as a collective business mentor (new requirement, 2026-10-20; not started; LAST)

**Status (2026-10-23): not started, and stays last.** Next step: only after every other item is finished.

Owner brief stored verbatim in [requirements/infodot-landing-page.md](requirements/infodot-landing-page.md): rewrite and
redesign the landing page around the three pillars (learn from collective experience, build and grow through the
business lifecycle, measure progress), with intent-based entry points that lead to real routes, honest integration
claims and no invented testimonials or statistics.

**Owner instruction: this is the last item on the list, to be done after everything else is finished.** It depends on
what exists by then: the Q&A pages, R-9 (community rewards) and the real Dot.Tasks / Finance / Analytics / Memory /
Brain integrations decide which claims the page may make. First step: inspect the current landing page, routes and
integrations and map each section to a real feature.

## R-11 Dot.Brain Brand Feeling Engine (new requirement, 2026-10-23; foundation merged)

Owner brief stored verbatim in [requirements/dot-brain-brand-feeling-engine.md](requirements/dot-brain-brand-feeling-engine.md):
a native brand-intelligence capability inside Dot.Brain, with versioned brand profiles for Blupin, InfoDot and the Dot ecosystem, a
brand-experience framework, analysis and implementation workflows, a transparent Brand Experience Quality Score (BXQS), Memory and
Dopemine integration where supported, evidence tracking, and a Claude Skill that is only an interface to Brain.

**Architecture assessment (verified in the Dot.Brain repository, 2026-10-23).** Dot.Brain is not a running application:
`brain.api.md` is a specification with no HTTP server, and its services are zero-dependency Node packages run by the owner or by
GitHub Actions. The Knowledge Pack format (DKP) is a specification with no signer or ingester, so nothing can honestly be called a
signed pack. The repository is public.

**Decisions (recorded in Dot.Brain ADR-0021, accepted 2026-10-23).**
1. One service, `services/brand-engine/` (Node library and CLI), and one data directory, `brand/` (principles, profiles, later
   rubrics), with two schemas; knowledge data is reviewed by path through CODEOWNERS. Not a separate application, and not a Skill that
   holds its own copy of the knowledge.
2. The framework and the brand-level profiles (`dot-ecosystem`, `infodot`, `blupin`) live in Dot.Brain; a product keeps its own
   profile in its own repository and extends a Brain profile by `id@version`.
3. Every assertion carries an evidence class (`documented_research`, `observed_behaviour`, `internal_hypothesis`,
   `validated_finding`, `declared_intent`) and the validator enforces what each class requires. Feelings are intended, never measured.
4. No LLM inside the engine: rules, schemas and hashes. Generative design happens in the Claude session.
5. The engine recommends and never ratifies: an AI session may create `draft` and `proposed`; only a human approver ratifies, by
   merging a pull request. Nothing is ratified.
6. Version 1 covers Dot-owned and first-party products only; no customer brand is stored (Dot.Brain is public and has no tenant isolation).
7. Dot.Memory use (assessment history only, opt-in) is deferred; Dot.Dopemine is reference-only (its ethics constraint applies to the
   habit and retention principles). Domain safety, regulation, accessibility, security and correctness outrank brand rules.

**Status (2026-10-23): partly done.** Merged in Dot.Brain: the foundation (#30: ADR-0021, the doctrine, two schemas, the profile
registry and validator, 12 framework principles, and draft profiles for `dot-ecosystem`, `infodot` and `blupin`; **nothing is
ratified**, every profile is `draft`) and #31 (stop tracking a local cache). **Open:** Dot.Brain #32, the BXQS rubric and scoring
engine; until it is merged and ratified no score exists and none may be quoted. **Not started:** the experience analysers, the
Claude Skill, an Actions workflow for the engine, Memory assessment history, the four demonstrations the brief asks for.
**Open questions** (from ADR-0021): whether branch protection requires a CODEOWNERS review on `/brand/`; which InfoDot design
document governs (the UI design guide and the product and design files conflict); what Blupin's product and audience are; three
citations are unverified.

First step for whoever continues: the owner reviews and ratifies (or edits) the draft profiles and principles in a pull request.

## Later phases

Design system, integration contracts, Brain/Memory/Dopemine governance, compliance matrix (POPIA, GDPR), disaster
recovery drills, investor due-diligence pack: each written when its evidence exists.

### R-1: the account's actual limits (cPanel Resource Usage, read by the owner 2026-10-19 evening)

| Resource | Limit | Reading (one idle snapshot) |
|---|---|---|
| Physical memory | **1 GB** | 550.8 MB (53.8%) |
| Number of processes | **100** | 10 (10%) |
| Entry processes | **30** | 1 (3.3%) |
| CPU | 100 | 2 |
| Disk | 29.3 GB | 12.34 GB (42%) |
| IOPS / I/O | 1,024 / 48.8 MB/s | 0 |

This confirms the process-limit explanation and adds the likelier killer: **one gigabyte of RAM for 27 subdomains.**
An idle account already sits at about 550 MB (web workers), a Laravel `artisan` process is tens of megabytes,
and `composer install` during a deploy is far more. Bursts that cross 1 GB get SIGKILL (exit 137: the killed
scheduler runs and the `composer install` that left Tasks in maintenance mode); a burst of more than 100 processes
gives "fork: Resource temporarily unavailable" (exit 125). The runner's parallelism of 4 is therefore a memory
choice as much as a process one; if a pass ever shows 137 again, lower `DOT_CRON_PARALLEL` to 3 before anything
else. The numbers are one snapshot, not a peak: the Resource Usage graphs for the failing minutes would show the
peak. Scale implication for the 1M-user goal: this account cannot be the production platform for that target.

### R-1 status (2026-10-19 18:55 UTC)

All 25 apps run through the runner; 0 command failures logged in the 19 minutes since the last batch moved, against
13 in 11 minutes before the rollout started. One caveat for the next change: a pass now takes about 49 s, started at
second 30, so it ends around second 19 of the next minute. Another 10 to 15 s of work would make passes overlap
(the schedule-pass lock then skips a minute and writes `last-skip.log`). Mines' `schedule:run` alone takes 20 to 23 s.
The older apps' Scheduler Watchdog runs since 18:30 all succeeded, but only 1 to 2 runs per app are in that window
(GitHub's scheduler was degraded), so the 48 h comparison against the pre-18-Oct baseline is still outstanding.
R-1 stays open until that comparison and the cPanel Resource Usage reading are in. The process-limit hypothesis is
unconfirmed.

### R-1 status (2026-10-23, read from GitHub Actions)

R-1 stays **partly done**. The runner carries all 25 apps (2026-10-19, above) and no scheduler change was made since. The
acceptance asks for a Scheduler Watchdog failure rate under 2%. Failed runs among the most recent 200 runs of each repository's
Scheduler Watchdog, queried 2026-10-23 (the 200 runs begin between 2026-10-20 13:20 UTC and 2026-10-21 10:37 UTC, so each window
is about 2.5 to 3.2 days, not exactly 48 h):

| Failed of 200 | Repositories |
|---|---|
| 11 (5.5%) | Forms |
| 9 (4.5%) | Engage |
| 8 (4.0%) | Agents |
| 7 (3.5%) | Mines, Press, Sheet |
| 6 (3.0%) | Dopemine, Memory |
| 3 (1.5%) | Central, Projects, Charts |

So the target is met for three of the eleven apps that have a watchdog and missed for eight. This is far below the failure
counts in the R-1 evidence paragraph (for example Sheet, 24 of 74 runs) but not low enough. The reason each failed run failed has not been
read, so this update does not say whether the cause is the host, a deploy window or GitHub. Still outstanding: the cPanel Resource
Usage reading for a failing minute (the 2026-10-19 reading was one idle snapshot) and the burst sample after the runner.
