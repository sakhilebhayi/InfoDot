# Execution log

## 2026-10-18 (before this programme document set)
- Merged and deployed the 13-platform completion work; deployed Dot.Analytics; InfoDot marks all 14 active
  (InfoDot PRs #35, #36, #37, #38; Dot.Analytics PR #22). Details: `docs/platform-completion-register.md`.
- Host dropped SSH connections during 9 failed deploy attempts (Emall 2, Tutor 2, Analytics 2, HR, Pulse, Plug; exit 255 / "exec request failed"); each deploy passed on a later attempt.

## 2026-10-19: baseline increment
- Inventoried 27 Dot repositories, probed every host, read CI and PR state (ECOSYSTEM_INVENTORY.md).
- Ran `composer audit` / `npm audit` on 26 default-branch lockfiles: advisories in 17 repositories (SECURITY_ASSESSMENT.md S-1).
- Found the scheduler-staleness regression (REMEDIATION_ROADMAP.md R-1).
- No application code changed in this increment. Deployments: none.

## 2026-10-19: R-1 diagnosis
- Added the read-only Host Health workflow (InfoDot PR #40); ran it twice. Found the process-limit burst (`fork: Resource temporarily unavailable`, 57 artisan processes at t+7 s).
- Opened 13 cron-spread PRs (see REMEDIATION_ROADMAP.md R-1). Not merged, not deployed.
- Merged the 13 cron-spread PRs and deployed the 13 platforms one at a time (Tasks needed one re-run: the host killed `composer install` after maintenance mode started, site down about 7 minutes; Emall and several others needed retries after SSH drops; a GitHub Actions incident queued Finance for about 25 minutes and hung Farms' post-run cleanup). All 13 end healthy (/ /login /up 200, POST /up 405).
- Re-ran Host Health: burst still reaches the process limit (51 artisan processes at t+7 s). R-1 is open; next step is the bounded-concurrency runner.
- Rollout step 1: merged the runner, installed it (two failed installs were rolled back by the read-back check: the host shell has no /dev/fd), merged the 13 runner-mode pipeline PRs, migrated tasks, notify, pulse. Pilot result: scheduler commands killed (rc 137 / 125) in most minutes while the 39 other lines still burst at second 0. The other 10 new apps and the older apps are NOT migrated. See REMEDIATION_ROADMAP.md R-1.
- Migrated the 10 other new apps (13 on the runner), merged the diagnostics (#46), the second-30 start and the pilot docs (#47, #48), refreshed the runner. Failure counts: see REMEDIATION_ROADMAP.md R-1.
- Opened 12 runner-mode PRs for the older apps (Mines, Agents, Central, Press, Forms, Engage, Sheet, Projects, Memory, Dopemine, docs, Charts). Not merged, not deployed.
- Merged the 12 older-app runner PRs and migrated them in two batches (watched 10 minutes each, 0 failures). Crontab now: runner line + Charts keepalive only.
- R-2: opened 17 composer-update PRs (audit clean, CI green), 2 axios PRs, a Charts backend PR (48 advisories found there), and the weekly Dependency Audit workflow. Nothing merged or deployed.
- 2026-10-19: new requirement received (InfoDot community contributions and knowledge rewards); stored in requirements/infodot-community-rewards.md and added to the roadmap as R-9. Not started.
- cPanel limits read: 1 GB RAM, 100 processes, 30 entry processes (R-1). Merged all R-2 PRs (17 composer, 2 axios, Charts backend) and docs #51/#52; deploying them one app at a time.
