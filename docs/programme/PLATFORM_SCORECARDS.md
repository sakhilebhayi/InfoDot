# Platform scorecards (baseline 2026-10-19)

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
| 13 platforms deployed 2026-10-18 (Tasks, Auction, Ehail, Notify, Farms, Finance, Emall, Billing, HR, Tutor, Design, Pulse, Plug) | U (no end-to-end browser run; pages load) | **F** (open advisories, S-1) | P (CI green) | U (never load-tested; SQLite) | **F** (backup restore untested; no scheduler monitor; host cron load, R-1) | U |
| Analytics | U | **F** (axios high) | P | U | **F** (as above) | U |
| Older live apps (Mines, Files, Memory, Dopemine, Projects, Agents, Central, Charts, Engage, Forms, Press, Sheet, docs) | U | F for Files, Memory, Dopemine, Projects (S-1); U for the rest | P/U (CI differs per repo) | U | **F** (watchdog failures, R-1); Files has 18 open PRs | U |
| Brain | U | U | P (tests workflow) | n/a | U | U |

Every group currently fails at least one mandatory gate, so **no platform is classified enterprise-ready.**

## Dot.Mines as benchmark

Not yet evaluated against the brief's checklist. The scorecard acceptance criteria will be written from that
review (roadmap item R-6), not assumed.
