# Security assessment (baseline 2026-10-19)

Scope of this baseline: **dependency advisories only**, plus configuration facts already verified during the
13-platform deployment. No penetration test, no manual authorisation review of the 14 platforms outside that
work, no host hardening review and no secrets-in-history scan has been done. Absence of a finding below is not
a security claim.

## S-1 Dependency advisories (verified: `composer audit --locked`, `npm audit --package-lock-only --omit=dev`, default-branch lockfiles, 2026-10-19)

| Package | Advisory | Severity (feed) | Notes |
|---|---|---|---|
| guzzlehttp/guzzle (Pulse) | CVE-2026-69246 noncanonical host bypasses host checks | **high** | plus 5 medium cookie/redirect/proxy-header advisories (CVE-2026-69245, -67354, -67355, -67353, -67339) |
| livewire/livewire | CVE-2026-81887 DOM-based XSS in client-side state handling | medium | affects Livewire 3.x up to the fixed release; installed 3.8.2 in the sampled repo |
| laravel/framework | CVE-2026-102279 XSS in the debug page | low | the deploy pipelines force `APP_DEBUG=false`, which removes the exposure |
| league/flysystem | CVE-2026-102601 path-normaliser control-character issue | low | |
| symfony/http-foundation | CVE-2026-108082 ReDoS in `HeaderUtils::split()` | unrated | |
| symfony/yaml | CVE-2026-109413 unbounded recursion in inline lexer | unrated | exposure depends on parsing untrusted YAML |
| axios (npm, prod) | 1.0.0 to 1.19.0 range | **high** | Analytics, Files; `npm audit fix` available |

Repositories with advisories (17): Auction, Billing, Design, Dopemine, Ehail, Emall, Farms, Files, Finance, HR,
Memory, Notify, Plug, Projects, Pulse, Tasks, Tutor (4 to 5 advisories each; Pulse 10). Clean in this audit (9):
Agents, Analytics (composer), Central, Engage, Forms, Mines, Press, Sheet, docs. Dot.Charts has no root
`composer.json` and was not audited. Dot.Brain (JavaScript) was not audited.

Remediation: `composer update` of the named packages (with dependencies), full suite per repository, PR, deploy.
Tracked as R-2 in the roadmap. No upgrade is applied to production without the suite and the deploy smoke check.

## S-2 Verified controls (from the 13-platform work, 2026-10-18)

- Production invariants forced by every deploy: `APP_DEBUG=false`, `APP_ENV=production`.
- Smoke check on every deploy: POST `/up` must answer 405; `/<key>/.env`, `artisan` and the SQLite file must not be
  downloadable (all 13 returned 403 on 2026-10-18).
- Signed server-to-server calls (HMAC-SHA256 over timestamp + body) for Notify, Analytics and Billing; unsigned
  Analytics ingest answers 401 (verified).
- Sign-in redeem refuses a wrong bearer (422) and platforms not marked active.
- Paystack code refuses `sk_live_` keys; webhook signatures and idempotency are tested in the repositories.
- Secrets live in a mode-600 local file and host `.env` files; they were not committed (GitGuardian passes on the
  InfoDot and Analytics PRs).

## S-3 Not yet assessed (no evidence either way)

Tenant-isolation test coverage on the older platforms, MFA availability and enforcement, rate limiting on login and
API routes, security headers and CSP, file-upload handling beyond Design and Emall, backup encryption, log
redaction, admin/operator privilege boundaries on the older platforms, dependency review for Dot.Brain, WAF
behaviour of the hosting edge (a `waf-probe` workflow exists in Dot.Brain), and every item in the brief's sections
8.5 and 8.6 (SAST, SBOM, licence checks, incident procedures, disclosure policy).
