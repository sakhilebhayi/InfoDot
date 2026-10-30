# R-22 — Execution matrix (2026-10-30, end of first execution pass)

Statuses use the brief's vocabulary. Owner briefs: `requirements/dot-final-launch-execution.md`.
Owner steps: `runbooks/owner-actions-2026-11.md`. Evidence lives in the PRs and in
`R21_COMPLETION_REPORT.md`.

## Priority status

| # | Priority | Status |
|---|---|---|
| 1 | Domain renewal | BLOCKED — OWNER ACTION REQUIRED (registry-verified: expires 2026-11-27 13:53 UTC; registrar Key-Systems via the hosting reseller; steps in the owner sheet) |
| 2 | SSO client + Dot.Central pilot | BLOCKED — OWNER ACTION REQUIRED (must run as its own session; both launch attempts from this session were denied by the permission layer; paste-ready prompt in the owner sheet §6) |
| 3 | Restore drill / live Paystack | BLOCKED — OWNER ACTION REQUIRED (runbooks ready; Paystack step 0 = confirm where Dot.Billing runs in production — its repo has no deploy pipeline) |
| 4 | DNS + provider security | BLOCKED — OWNER ACTION REQUIRED (exact staged records in R-18 register §B + owner sheet; verification probes ready here) |
| 5 | Fleet security headers | IMPLEMENTED — NOT VERIFIED (15 PRs open, suites green; live verification happens at each deploy) |
| 5b | Fleet SSO migration | NOT STARTED (gated on the pilot) |
| 6 | Legacy auth retirement | NOT STARTED (gated on the last wave) |
| 7 | CSP monitoring | IN PROGRESS, clean (0 `csp.violation` in production logs at ~75 min and ~95 min post-enforcement checks; window continues ~48h) |

## 19-platform matrix

Domains preserved by construction everywhere. "Headers" = enforced baseline (nosniff, frame,
referrer, permissions, HSTS) + report-only CSP + /csp-report endpoint.

| Platform | Headers | Central SSO | Legacy login removed | Notes / decision needed |
|---|---|---|---|---|
| InfoDot | DEPLOYED — LIVE VERIFIED (enforced nonce CSP) | is the authority | n/a | CSP report endpoint hardened in this PR (array-proof, capped keys) |
| Dot.Mines | already live (pre-existing) | NOT STARTED | registration env-gated | — |
| Dot.Agents | already live (pre-existing) | NOT STARTED | NOT STARTED | — |
| Dot.Central | deferred to pilot session | pilot NOT STARTED | NOT STARTED | headers ride with the pilot to avoid two writers on one repo |
| Dot.docs | PR [#103](https://github.com/sakhilebhayi/Dot.docs/pull/103) — **merge = deploy** | NOT STARTED | NOT STARTED | dependency audit red: symfony/mime CVE-2026-45070 (pre-existing) |
| Dot.Sheet | PR [#71](https://github.com/sakhilebhayi/Dot.Sheet/pull/71) | NOT STARTED | NOT STARTED | — |
| Dot.Forms | PR [#58](https://github.com/sakhilebhayi/Dot.Forms/pull/58) | NOT STARTED | NOT STARTED | DECISION: SAMEORIGIN frame protection blocks embedding public forms on other sites (no embed feature found in code) |
| Dot.Engage | PR [#63](https://github.com/sakhilebhayi/Dot.Engage/pull/63) | NOT STARTED | NOT STARTED | DECISION: Permissions-Policy/frame-src allow `*.daily.co` so in-app video keeps working; verify one real call post-deploy |
| Dot.Press | PR [#67](https://github.com/sakhilebhayi/Dot.Press/pull/67) | NOT STARTED | NOT STARTED | quality gate red on pre-existing symfony/mime advisory; YouTube/Vimeo slide embeds allowed in frame-src |
| Dot.Memory | PR [#46](https://github.com/sakhilebhayi/Dot.Memory/pull/46) | NOT STARTED | NOT STARTED | — |
| Dot.Dopemine | PR [#49](https://github.com/sakhilebhayi/Dot.Dopemine/pull/49) | NOT STARTED | NOT STARTED | — |
| Dot.Charts | PR [#29](https://github.com/sakhilebhayi/Dot.Charts/pull/29) | NOT STARTED | n/a (API tokens) | static docroot gets headers via an idempotent `.htaccess` splice step in deploy; verify at first deploy |
| Dot.Emall | PR [#32](https://github.com/sakhilebhayi/Dot.Emall/pull/32) | NOT STARTED | registration closed, LIVE | Paystack is server-side redirect — no CSP allowance needed |
| Dot.Ehail | PR [#32](https://github.com/sakhilebhayi/Dot.Ehail/pull/32) | NOT STARTED | registration closed, LIVE | — |
| Dot.Auction | PR [#33](https://github.com/sakhilebhayi/Dot.Auction/pull/33) | NOT STARTED | registration closed, LIVE | — |
| Dot.Projects | PR [#36](https://github.com/sakhilebhayi/Dot.Projects/pull/36) | NOT STARTED | registration closed + token-URL dead, LIVE | if the host docroot serves the repo-root "Coming Soon" index.html, that static page bypasses Laravel headers — check at deploy |
| Dot.Tasks | PR [#33](https://github.com/sakhilebhayi/Dot.Tasks/pull/33) | NOT STARTED | registration closed, LIVE | — |
| Dot.Design | PR [#29](https://github.com/sakhilebhayi/Dot.Design/pull/29) | NOT STARTED | registration closed, LIVE | — |
| Dot.Tutor | PR [#26](https://github.com/sakhilebhayi/Dot.Tutor/pull/26) | NOT STARTED | NOT STARTED | oldest repo; suite green |

All 15 PRs: enforced baseline now, CSP strictly report-only (flip per platform after log review at
its SSO-wave visit), rollback = revert (HSTS alone: `SECURITY_HSTS_SECONDS=0`). None deploys on
merge except Dot.docs. Perch Scan fails on every PR as it does fleet-wide (not a required check).

## New findings from this pass (follow-ups, not blockers)

1. **Tailwind Play CDN in production layouts** (Tasks, Design, Tutor, Emall, Ehail, Auction,
   Projects, Dopemine, Memory): `cdn.tailwindcss.com` is a dev tool, and it is the one third-party
   script origin most platforms need. Building CSS properly removes it and tightens every CSP.
2. **symfony/mime CVE-2026-45070** (advisory 2026-10-28) fails dependency audits on Dot.docs and
   Dot.Press — a fleet `composer update symfony/mime` pass is due.
3. **ui-avatars.com** is a real runtime dependency on most platforms via Jetstream's default
   avatar, even where profile photos are off.
4. Second Alpine from `unpkg.com` on three welcome pages (Livewire already bundles Alpine) —
   removable, drops an origin.
5. `trustProxies` is unconfigured fleet-wide; on this host PHP sees HTTPS directly so HSTS should
   send — the deploy-time header check proves it either way.

## Immediate merge guidance

Safe to merge now (manual deploy, verified suites): Sheet, Forms*, Engage*, Press, Memory,
Dopemine, Charts, Emall, Ehail, Auction, Projects, Tasks, Design, Tutor (*after the two decisions
above). Dot.docs merges when you are ready for it to deploy. Live header verification runs here
after each platform's deploy.
