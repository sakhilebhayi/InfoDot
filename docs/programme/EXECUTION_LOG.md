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
