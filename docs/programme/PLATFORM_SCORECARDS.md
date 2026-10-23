# Platform scorecards (baseline 2026-10-19, release-gate corrections 2026-10-23)

## Rubric (from the programme brief, 100 points)

Functional completeness 20, security and privacy 20, reliability and operational readiness 15, scalability and
performance 15, UI/UX and accessibility 10, testing and maintainability 10, commercial and investor readiness 10.
Target: at least 90 with no mandatory release-gate failure.

**No platform has been given a 100-point score yet.** Most dimensions have no measurement behind them (no load
test, no restore test, no accessibility run, no tenant-isolation review outside the 13 platforms). A number
produced now would be a guess, so each platform is reported by release gate instead.

## Release-gate status

Legend: P = evidence exists and passes; F = evidence of a failure; U = unverified (no evidence either way).
Quality-gate "tests" means a CI workflow exists and its last default-branch run passed (2026-10-19).

| Platform group | Product | Security | Quality | Performance | Operations | Commercial |
|---|---|---|---|---|---|---|
| 13 platforms deployed 2026-10-18 (Tasks, Auction, Ehail, Notify, Farms, Finance, Emall, Billing, HR, Tutor, Design, Pulse, Plug) | U (no end-to-end browser run; pages load) | **F** (S-1 advisories cleared 2026-10-19; now F for the app folder answering 200 after some deploys, S-2d, and archives in the public web root, S-2f) | P (CI green) | U (never load-tested; SQLite) | **F** (backup restore untested; no scheduler monitor; host cron load, R-1) | U |
| Analytics | U | **F** (axios advisory cleared 2026-10-19; the S-2f and S-2d exposure findings are the remaining evidence of failure) | P | U | **F** (as above) | U |
| Older live apps (Mines, Files, Memory, Dopemine, Projects, Agents, Central, Charts, Engage, Forms, Press, Sheet, docs) | U | F for Dopemine, Engage, Memory, Sheet (database and logs served over HTTP until 2026-10-20, S-2c) and for Forms, Engage, Agents, Sheet, Press, Projects, Central (archives in the web root, S-2f); S-1 advisories cleared 2026-10-19; U for the rest | P/U (CI differs per repo; Dopemine, Memory and Charts gained test CI on 2026-10-22) | U | **F** (watchdog failures, R-1); Files has 18 open PRs | U |
| Brain | U | U | P (tests workflow) | n/a | U | U |

Every group currently fails at least one mandatory gate, so **no platform is classified enterprise-ready.**

## Dot.Mines as benchmark

Not yet evaluated against the brief's checklist. The scorecard acceptance criteria will be written from that
review (roadmap item R-6), not assumed.

## Corrections, 2026-10-23

Only evidence-based cell changes; no 100-point score has been added. The security column stays F because evidence of failure remains (the S-2d window after some deploys, the S-2f archives, secrets not yet rotated); the reasons changed, not the gate result. The operations column stays F: the Backups tooling is merged but never run and no restore is measured (R-3), and the Scheduler Watchdog failure rate is under 2% for only 3 of 11 apps (R-1). Sign-in, registration closure and the read-only role (R-5, R-7) are product and security progress with no release-gate cell changed by them yet, because no end-to-end browser run is recorded.
