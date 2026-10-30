# R-24 — Production readiness & launch execution report (2026-10-31)

The owner's brief is verbatim in `requirements/infodot-launch-execution.md`. Statuses use the
brief's vocabulary. Everything marked verified was checked THIS day against production, the
registries, or the running application — not inherited from older reports.

## §1 Status register (claims re-verified 2026-10-31)

| Claim | Status | Evidence |
|---|---|---|
| Test suite + CI gates | VERIFIED | 1,350 passing locally this day; 11 required checks on protected main |
| Enforced nonce CSP, zero violations | DEPLOYED — LIVE VERIFIED | live header check + two production log sweeps (0 `csp.violation`) |
| Security headers / sessions / hashing / security.txt / disclosure page | DEPLOYED — LIVE VERIFIED | live curls this day |
| OIDC identity provider | DEPLOYED — LIVE VERIFIED | discovery + JWKS live; 20 provider tests |
| Satellite registration closed; token-URL login dead | DEPLOYED — LIVE VERIFIED | live probes on all six commerce platforms |
| Backups nightly + restore tooling | IMPLEMENTED — NOT VERIFIED | **restore drill never run** — the gap is the drill, not the tooling |
| Deploy pipeline + rollback | VERIFIED | used repeatedly this day |
| Rich media / tried-it / mentions / versioning / duplicates | DEPLOYED (browser-verified at build time) | never used by real users |
| Seminars, tiers, licensing | DEPLOYED | never exercised commercially; Paystack never live |
| 20-entry sourced library | DEPLOYED — LIVE VERIFIED | host seed output + post-seed log sweep |
| Dot.Billing deployed | VERIFIED (corrected) | live at billing.infodot.co.za; own pipeline, 12 deploys since 2026-10-18; **stale-checkout claim "no pipeline" was wrong** |

## §2 Critical priorities

| # | Item | Status | Next action |
|---|---|---|---|
| P0 | Domain renewal | **BLOCKED — OWNER** | whois re-verified this day: still expires **2026-11-27 13:53 UTC**. Steps: `runbooks/owner-actions-2026-11.md` §1. ~10 min |
| P1 | Restore drill | BLOCKED — OWNER | runbook ready; ~30–45 min on the host; paste output here for verification |
| P1 | Live Paystack | BLOCKED — OWNER | preconditions now exact (runbook §0, updated from the Billing code audit): live key + `PAYSTACK_ALLOW_LIVE=true` on the Billing host, dashboard webhook URL, coordinated window. Billing's headers PR [#37](https://github.com/sakhilebhayi/Dot.Billing/pull/37) open |
| P1 | DNS/email | BLOCKED — OWNER | records ready (R-18 §B + owner sheet §2); verification probes run from here afterwards |
| P1 | SSO client + Central pilot | BLOCKED — OWNER (session start) | this session is barred from generating it (4 safety-layer stops incl. delegation); paste-ready prompt: owner sheet §6 |

## §4–5 Ecosystem

Headers: InfoDot, Mines, Agents live; 15 platforms MERGED — NOT DEPLOYED (deploy pairs with each
platform's SSO wave, or sooner on request); Dot.docs #103 OPEN (merge = deploy); Billing #37 OPEN.
SSO migration + legacy retirement: NOT STARTED (gated on the pilot).

## §6 Landing page (R-10) — DEPLOY PENDING in this PR

The page was already rebuilt earlier in the programme (search-led hero, intent entry points,
honest pillars/trust, platform wall — all mapped to real routes). This pass closed its one real
gap: **the researched library is now showcased** as a Yard ledger — six latest official entries
with their real references, step counts and topics, a true stencil tally from the database, an
empty-state guard, and an honest note that reading requires the free sign-in. Verified in the
browser (desktop + mobile, dark) against the 20 real entries; design detector clean on the new
code; `LandingLibraryTest` pins real-rows-or-nothing. Remaining acceptance items (metadata,
responsiveness, accessibility, honest copy) were already in place from R-17/the earlier build.

## §7 First users

Onboarding walked end-to-end this day on a clean account: register → verify → personal workspace
created → Home shows a waiting public question, the live solutions ledger and search → ask flow
one click away. It is genuinely usable. Register of follow-ups (not blockers): a dismissible
first-run "three things to do first" strip; clarify the "question is waiting for an answer"
headline when the question belongs to another company; the launch/community strategy (§7B) is an
OWNER activity — the platform is ready for it, the outreach cannot be engineered from here.
Feedback loop: the UX-signals instrumentation (R-12 groundwork) plus the contact page exist; a
lightweight "did this solve it?" prompt already exists in tried-it. Adequate to launch; iterate
with real users.

## §8 Commercial model

Tiers/seats/licensing implemented and tested in CI (R-13); entitlements flow InfoDot↔Billing via
the signed API. UNVALIDATED commercially: no live transaction has ever run (see P1). Free tier
keeps Q&A + library reading accessible. Status: IMPLEMENTED — NOT VERIFIED until the Paystack
window runs.

## §9 Continuity — `runbooks/operational-continuity.md` (new)

Access map (where credentials live — no secrets in docs), recovery procedures and priorities,
single points of failure. The one urgent action it surfaces: **confirm an offline copy of the
backup passphrase exists away from the host** — without it, backups are unreadable after a host
loss.

## §10 Measurement framework

Already measurable from real data today: registered users/businesses, questions and answer
rates (the analytics dashboard ships solve-rate and volumes), solutions viewed, tried-it
confirmations, verified implementations, library usage via search; commercial numbers arrive with
Paystack. Decision: NO new dashboard is built before there are users — the brief's own rule (no
fake baselines, no vanity instrumentation) argues against pre-building; the existing analytics
page + the feedback loop carry the first cohort, and the framework (metric list §10 of the brief)
is adopted as the definition for when real data exists.

## Final launch recommendation

Engineering is launch-ready. The launch gate is four owner actions, in order: renew the domain
(10 min, hard deadline 27 Nov); run the restore drill (45 min); run the Paystack window (~1h,
with this session verifying server-side); apply the DNS records (20 min). The SSO pilot and
fleet waves are post-launch hardening, not launch blockers: InfoDot's own sign-in is complete.
The genuine remaining risk is adoption, not infrastructure — the next milestone is the first
real user, and that is a marketing act, not a code change.
