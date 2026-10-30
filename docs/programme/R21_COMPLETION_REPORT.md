# R-21 — Enterprise Completion Report (2026-10-30)

The owner's brief is verbatim in `requirements/dot-enterprise-completion.md`. This report states
what is DONE with evidence, what is OPEN with a named owner or next step, and corrects two stale
discovery findings. Nothing is marked complete without a test, a live probe, or a command output.

## Status matrix

| Area | Status | Evidence / next step |
|---|---|---|
| Dot Identity (provider) | **COMPLETE, LIVE** | PR #180 deployed; discovery+JWKS live; 20 tests |
| SSO relying-party client | **OPEN — blocked in this session** | package groundwork + README spec committed; the sign-in code was repeatedly withheld by the assistant's safety layer and may not be regenerated from that session; run it as a fresh session from `packages/dot-sso-client/README.md` |
| Dot.Central pilot | **OPEN** | follows the client package |
| Platform migration waves | **OPEN** | after pilot; pair each platform's wave with its security-header rollout (one deploy per platform) |
| Legacy token-in-URL (Projects) | **ALREADY FIXED, LIVE** | `sec/sso-get-removal` is merged (0 ahead) and production probes confirm: `GET /auth/ecosystem?token=…` → 302 /login on all six platforms |
| Public registration (6 platforms) | **ALREADY CLOSED, LIVE** | `sec/close-registration` merged everywhere; `/register` → 302 /login live on projects, emall, ehail, auction, tasks, design |
| CSP enforced | **COMPLETE, LIVE** | enforced nonce policy live; 0 `csp.violation` in production logs since deploy; 0 browser console violations; keep watching ~48h |
| Vulnerability disclosure policy | **COMPLETE (this PR)** | `/security` page + footer links; security.txt gains `Policy:`; SeoTest pins both |
| security.txt RFC 9116 | **COMPLETE** | Contact, Policy, Expires 2027-04-01, Preferred-Languages, dual Canonical; expiry watched by ops:check |
| Domain renewal | **OPEN — OWNER, HARD DEADLINE 2026-11-27** | registrar action; verify auto-renew + billing |
| SPF | **LIVE** | `v=spf1 +a +mx +ip4:198.38.92.233 include:spf.mysecurecloudhost.com ~all` (softfail) |
| DKIM | **LIVE** | `default._domainkey` RSA key answers |
| DMARC | **PARTIAL — OWNER** | record exists but `p=none` and no `rua=`; add reporting address, monitor, then move to quarantine (R-18 register has exact values) |
| CAA | **OPEN — OWNER** | no CAA record; add issuers per R-18 register (must include the CA AutoSSL uses) |
| DNSSEC | **OPEN — OWNER** | zone unsigned (no DS); registrar/host step |
| OpenSSH / FTP tickets | **OPEN — OWNER** | provider tickets (R-18 register) |
| MySQL restore drill | **OPEN — OWNER (launch blocker)** | host-side; runbook in R17 docs; record RPO/RTO |
| Live Paystack verification | **OPEN — OWNER (launch blocker)** | real payment + webhook path end to end |
| Security headers fleet | **OPEN — quantified** | live census 2026-10-30: 19 deployed platforms, headers on 3 (infodot, mines, agents), **none on 16** (central, doc, sheets, forms, engage, pres, memory, dopemine, charts, emall, ehail, auction, projects, tasks, design, tutor); roll out per platform with its SSO wave |
| Dev seeders | **COMPLETE** | `php artisan migrate:fresh --seed` → DemoSeeder (idempotent, refuses production) + `knowledge:seed`; run twice locally, baseline restored |
| Regression suite | **GREEN** | full suite on this branch (count in PR), PHPStan level 5, Pint |

## Corrections to the discovery record

The 2026-10-30 discovery scouts read **stale local checkouts** (some 20–48 commits behind
origin/main, several on side branches). Two findings were wrong against the real remotes and
production:

1. `sec/sso-get-removal` and `sec/close-registration` are **merged on all six repos** (compare
   shows 0 ahead), and production behaves accordingly (probes above). The brief's R-20.4 items for
   these two findings were therefore already done before this report.
2. Those repos' deploy state is current with their mains for these changes — the live probes are
   the proof, not the checkouts.

Lesson recorded: inventory remotes (`gh api compare`) and production probes, never local trees.

## Remaining backlog, in order

1. **SSO relying-party package + Dot.Central pilot** — fresh session from the package README.
2. **Owner, before 2026-11-27: renew infodot.co.za.**
3. Owner launch blockers: restore drill, live Paystack test.
4. Owner DNS: DMARC rua + policy, CAA, DNSSEC; OpenSSH/FTP tickets.
5. Waves: per platform, SSO migration + security headers + (where applicable) Fortify
   registration closure, one deploy each; legacy `/auth/ecosystem` + InfoDot launch/redeem API
   retire only after the last wave (Phase 5), then ecosystem verification (Phase 6).

## Rollback

Everything in this PR is additive (a public page, a security.txt line, seeders, docs): revert the
merge. No migrations, no behaviour changes for signed-in users.
