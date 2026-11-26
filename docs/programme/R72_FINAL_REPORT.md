# R-72: enterprise readiness, final report and scorecard (phase R72-9)

Brief: `R72_BRIEF.md` (phases 12 and 13). Baseline and findings register: `R72_ENTERPRISE_AUDIT.md` (docs PR 314).
Measured on `main` after R72-1 to R72-7 (PRs 315 to 321) and the R72-9 follow-ups in this PR, 2026-11-26.

**Read this first.** Every score below is for the code on `main`. **None of R72 is deployed**: production still runs `1f382bf`,
which the baseline measured at 6.9. The live site earns these scores only after a backed-up deploy and the live checks in
section 14. Nothing here was measured on production, and no real data was touched.

## 1. Executive summary

| | Score |
|---|---|
| Baseline (production and main, 2026-11-26 morning) | **6.9 / 10** |
| Final, `main` after R72-1..R72-7 and the R72-9 fixes | **8.1 / 10** |
| Target in the brief | 9.5–10 |

No Critical issue was found at the start or at the end. No unresolved security vulnerability, tenant isolation failure,
data integrity risk or broken core workflow is known. The target is not reached, and the brief forbids awarding it without
evidence. What stands between 8.1 and 9.5 is mostly **proof that only the live system or the owner can give**: a deploy, a
real email arriving, a restore drill with a measured recovery time, a load test on a staging copy, and alerts reaching a
person. Section 14 lists them with the owner of each.

The verification was independent: three reviewers that had not done the fixes re-read the code (security; email, notifications
and operations; performance, code quality and functional integrity), given the original findings and the code, not the
conclusions. They found no High or Critical issue and five new small ones; four were fixed in this PR (section 2).

## 2. What the independent verification found, and what this PR fixed

| ID | Severity | Found | Status |
|---|---|---|---|
| N-1 | Medium | The scheduler heartbeat expired after two hours and then read as "never seen", so `/ready` went green again while the scheduler was dead | **Fixed and retested**: kept without expiry; a three-hour-old beat keeps `/ready` at 503 (`OpsAndMailFollowUpsTest`) |
| N-2 | Low | A reviewer-queue email whose send failed was never retried: the half-hour throttle was already taken | **Fixed and retested**: the throttle is given back on failure |
| N-3 | Low | The cron queue worker's `--timeout` equalled the queue's `retry_after` (90), so a slow send could be picked up twice | **Fixed and retested**: `--timeout=60` on both workers; a test keeps every worker's timeout below `retry_after` |
| E-05b | Low/Medium | The "email address changed" message put the raw account name into Markdown; an account taker could plant a link in the one mail the real owner gets | **Fixed and retested**: the name goes through `MailText::safe` |
| CI | Low | The browser job failed once on `main` with "database is locked" (SQLite, several processes) | **Fixed**: a 10-second busy timeout for the browser runs only (`DB_BUSY_TIMEOUT`) |
| P-20b | Medium | A directory search with a two-letter word ("co") matches nearly every business and loads every match | **Open**: needs the load test (R72-8) to size; "IT" is a real two-letter search, so it is not blocked blindly |
| S-04b | Low | Registration still says when an address is taken | **Open, owner decision**: hiding it changes sign-up for everyone |
| N-4 | Low | Nothing alerts when the nightly backup is stale | **Open**: needs the alert mailbox (O-02) |

## 3. Security and data protection (20%): 7.5 → **8.5**

- **Resolved and verified by the independent reviewer:**
  - Dependency gate blocks medium and unrated advisories and fails closed; `composer audit` reports 0 (S-01).
  - Moderator and platform-administrator powers require confirmed two-factor at one point every path uses (S-02).
  - Passwords need 12 characters and a breach check (S-03).
  - Forgot-password no longer reveals accounts (S-04).
  - Confirm and update throttles, the current password for an email change, and new-device sign-in alerts (S-05, S-06).
  - User text escaped in mail (E-05).
  - Atomic single-use SSO redeem.
  - Tenant isolation traced through eight company areas, always 404 before 403.
- **Tests:**
  - `PrivilegedTwoFactorTest`, `AccountHardeningTest`, `TwoFactorChallengeTest`, `MailTextEscapingTest`, `AccessPoliciesTest`.
  - The full suite: 3,167 passing in CI.
- **Outstanding:** registration enumeration (owner decision); Laravel's breach check lets a password through when the breach
  API cannot be reached (framework behaviour; confirm the host can reach it).
- **Not verifiable from code:** the production `.env`, live headers and cookies (verified on 2026-10-30, not re-checked since).

## 4. Functional correctness and regression stability (15%): 8.0 → **9.0**

- **Evidence:**
  - CI on `main`: 19 of the last 20 runs green; the one red was the SQLite lock, fixed here.
  - 3,167 tests, 9 incomplete, 4 skipped.
  - Line coverage 94.2%, now gated at 92%.
  - All 374 routes resolve to real handlers, 142 literal views exist, and every scheduled command exists (independent check).
- **Browser:**
  - The full desktop Chromium suite passes, 36 of 36 (baseline 22 passed, 8 failed, 6 not run).
  - The accessibility, keyboard and three-width sweeps run in CI on every change.
- **Fixed and retested:** stale specs (B-01, B-03), the Dot Apps Escape trap (B-02), licence dead end (F-01), size bands (F-04),
  sign-in labels (F-05), two-factor challenge tests (F-06), the flaky moderation test (O-05).
- **Not tested in this programme:** the Firefox, WebKit and phone browser projects (they run locally only); the 67 tests that
  fail only on the developer's machine (platform registry pointed at local copies; green in CI).

## 5. UX/UI quality and usability (15%): 7.0 → **8.5**

- **Evidence:** 87 signed-in screens, as the owner, a customer, a moderator and a platform administrator, at 1440, 768 and
  375 px. Each checked for: one main heading, no broken picture, no console error, no sideways scroll, nothing cut off at the
  edge. **0 findings**, in CI.
- **Fixed:**
  - Controls cut off on phones (filter rows), which the old overflow check could not see.
  - Directory spacing and photo shape.
  - Licence buttons that led nowhere.
  - Hero panel plate contrast.
- **Partially verified:** screenshots were reviewed by eye on phone width; no usability test with real business owners.

## 6. Accessibility (10%): 6.5 → **8.5**

- **Evidence:**
  - axe-core (WCAG 2.2 AA): 0 findings of any severity across the sweep.
  - The public and sign-up scan runs again (B-01), in both colour schemes for the owner and moderator pages.
  - Keyboard journeys pass, and every menu closes with Escape and returns focus.
  - Mail colours pass AA (E-13).
- **Not tested:** a screen-reader pass (VoiceOver, NVDA) and a manual WCAG review. The brief says an automated scan is not proof,
  so the score stops at 8.5.

## 7–8. Email and notifications (10%): 6.5 → **8.0**

- **Resolved (independent reviewer):**
  - Security and business notices are emailed as they happen (E-03).
  - New-device, suspension and restore, role, company-deleted, account-closed and welcome mails exist (E-04).
  - Mail is queued where waiting does not matter, and kept synchronous where the person must be told it failed (E-06).
  - Notices never reveal content the reader may no longer see (E-08).
  - Failures logged at error level and counted by `ops:check` (E-09).
  - InfoDot wording, settings links and South African time (E-13, E-14).
  - N-2 and N-3 fixed here.
- **Tests:** `NoticeEmailTest` and the mail tests, with a test per mailable.
- **Not proven:** a real email arriving. Production still sends synchronously until the deploy switches the queue, and the
  first live send (sign in from a new browser, confirm the mail) is an owner step.

## 9. Performance and scalability (10%): 5.0 → **6.5**

- **Resolved (independent reviewer):**
  - Directory paging is correct for any number of businesses (P-20).
  - Browsing runs no search (P-21).
  - Counts in SQL (P-24).
  - The answers N+1 (P-26).
  - Lazy loading logged on the live site (P-27).
  - The database cache with pruning (P-29).
  - Minute jobs side by side with a 5-minute lock (P-30).
- **Not measured:** no response time, throughput or memory figure exists for any page. The brief requires evidence, so this
  category stays capped until R72-8 (a seeded staging copy and a load test). The two-letter directory search (P-20b) and the
  `LIKE` scans are sized there.

## 10. Code quality and architecture (10%): 7.0 → **7.5**

- **Evidence:**
  - PHPStan level 5 clean, with the baseline halved: 134 → 65, and a ceiling that cannot grow.
  - Enquiry and booking policies; booking form requests that keep the 404/404/403/422 order.
  - One date parser; no dead classes left (independent sweep).
  - Line coverage 94%.
- **Outstanding:**
  - Most validation and ownership checks are still inline: correct and local, but not the Laravel convention.
  - `SeminarService` is 1,071 lines (Q-23).
  - `PointsClient` still parses dates its own way.

## 11. Infrastructure and operational reliability (10%): 6.0 → **7.5**

- **Evidence (code; not yet run against production):**
  - The deploy backs the database up and verifies it before maintenance mode, and stops if it cannot.
  - Maintenance mode must start.
  - The host is reset to the commit CI tested, refused unless it is on `main`.
  - Production settings are forced: debug off, production mode, database queue and cache, log level info.
  - The smoke check includes `/ready`; the uptime probe includes `/ready`.
  - Dependabot; a single runbook (`RUNBOOK.md`); `BACKUPS.md` corrected; the heartbeat fix (N-1).
  - A test guards the release order.
- **Blocked by the owner:**
  - The MySQL restore drill (O-01, so no measured recovery time).
  - The alert mailbox and Sentry (O-02).
  - A cloud copy and the passphrase in a password manager (O-09).
  - The host PHP version, an incident contact, a GitHub production environment (O-13..O-15).

## 12. Automated testing and CI/CD

| Gate | Result |
|---|---|
| PHPUnit (PHP 8.4, SQLite) | 3,167 passing, required |
| Migrations on MySQL, plus the demonstration content install, remove and restore check | required, green |
| Pint (whole repository), PHPStan level 5 (baseline ≤ 65), Composer gate (medium and up), npm audit (high and up) | required, green |
| Line coverage ≥ 92% (main 94.2%) | required, green |
| Browser: accessibility (both schemes), keyboard, three-width sweep | runs on every change; **not yet a required check** (owner adds it in branch protection) |
| Fake-host tests for cron, docroot guard, backups, access logs, POPIA inventory | required, green |

## 13. Final weighted scorecard

| Area | Weight | Initial | Final (`main`) | State |
|---|---:|---:|---:|---|
| Security and data protection | 20% | 7.5 | **8.5** | Fixed and retested; independently verified |
| Functional correctness and regression stability | 15% | 8.0 | **9.0** | Fixed and retested; independently verified |
| UX/UI quality and usability | 15% | 7.0 | **8.5** | Fixed and retested; partially verified (no user testing) |
| Accessibility | 10% | 6.5 | **8.5** | Fixed and retested; partially verified (no screen-reader pass) |
| Email and notification reliability | 10% | 6.5 | **8.0** | Fixed and retested; live delivery blocked until deploy |
| Performance and scalability | 10% | 5.0 | **6.5** | Fixed in code; not measured (R72-8 blocked by staging) |
| Code quality and architecture | 10% | 7.0 | **7.5** | Fixed and retested |
| Infrastructure and operational reliability | 10% | 6.0 | **7.5** | Fixed in code; drill and alerts blocked by the owner |
| **Weighted total** | 100% | **6.9** | **8.1** | |

(8.5×0.20 + 9.0×0.15 + 8.5×0.15 + 8.5×0.10 + 8.0×0.10 + 6.5×0.10 + 7.5×0.10 + 7.5×0.10 = 8.125.)

## 14. Outstanding risks and external dependencies (in order)

| # | What | Who | Lifts |
|---|---|---|---|
| 1 | Deploy `main` (backup first). Before it, switch on two-factor on the owner's own account, or moderator and admin powers stop | Owner says when; the pipeline does it | every score from production 6.9 to these |
| 2 | First live checks after the deploy: a new-browser sign-in email arrives; `/ready` 200; a moderator page with two-factor | Owner and the pipeline | Email to about 9, Security |
| 3 | MySQL restore drill into a scratch database, with timing (`runbooks/mysql-restore-drill.md`) | Owner creates the database | Operations to about 9 |
| 4 | `OPS_ALERT_ADDRESS` and the Sentry DSN in the host `.env`; then a stale-backup alert (N-4) | Owner | Operations |
| 5 | Staging subdomain with its own database; load test (R72-8) | Owner creates; then a PR | Performance to 8 or more |
| 6 | Browser job as a required check | Owner (branch protection) | Functional |
| 7 | Cloud backup copy and passphrase in the password manager (O-09) | Owner | Operations |
| 8 | Registration enumeration: keep or hide (S-04b) | Owner decision | Security |
| 9 | Split `SeminarService`; move more checks into policies | A PR when wanted | Code quality |
| 10 | Screen-reader pass of the main journeys | A person with VoiceOver or NVDA | Accessibility |

## 15. Ongoing maintenance and monitoring

- **Every change:** the CI gates in section 12 (they cannot be lowered: the baseline and coverage floors only go one way).
- **Every release:** the order in `RUNBOOK.md`; the run backs up itself and refuses an untested commit.
- **Daily (automatic):** the nightly backup at 02:40 and the Mac pull at 04:15; `uptime.yml` (`/up`, `/.env` and InfoDot's
  `/ready`); `ops:alert` every 10 minutes once the mailbox is set.
- **Weekly:** read the Dependabot pull requests (grouped) and merge on green; look at `performance.lazy_load` lines in the
  log and fix what they name.
- **Monthly:** `backups.yml mode=restore-test` and record the seconds in `BACKUPS.md`; check the domain and TLS dates.
- **Twice a year:** re-run this brief (`~/Dot/briefs/ENTERPRISE_READINESS_AUDIT.md`), and the knowledge library's review.

## What not to undo (for future work)

- `isModerator()` and `isPlatformAdmin()` include two-factor; check raw roles only to list or notify, never to grant.
- Never lower `phpstan-baseline.max` back up or `coverage-floor.txt` down; never add a CI exception without a written reason.
- `App\Entities\IdentityEntity` is loaded by name by the OIDC package; renaming it puts the numeric id in tokens.
- The deploy's order (backup, verify, maintenance mode, tested commit, migrations) is guarded by `ReleasePipelineTest`.
- Queue workers' `--timeout` stays below `retry_after` (tested).
- User text in mail goes through `MailText::safe`; a Blade `{!! !!}` in a Markdown mail needs a reason.
- The `.ix-filters` control wraps; the page clips overflow, so the three-width sweep is what catches anything cut off.
