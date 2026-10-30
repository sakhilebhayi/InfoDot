# R-18 — Security remediation register and report (2026-10-30)

The owner's brief is verbatim in `requirements/infodot-security-remediation.md`, driven by an
external scan of 2026-10-30 (rating C, 549/950). Every finding below was independently verified
against the live infrastructure on 2026-10-30 (curl/dig/whois/openssl/banner checks from this
session — connect-level only, no destructive testing). Statuses use the brief's vocabulary.

**The headline:** most "website security" findings are stale — the scan does not reflect the
enterprise-programme headers deployed on 2026-10-30 (or it graded subdomains, which are separate
apps without those headers). The genuinely open items are: **CSP enforcement (fixed in this
change set)**, **the domain expiring 2026-11-27 (urgent, owner)**, email policy tightening
(owner, exact records below), CAA/DNSSEC (owner), and host-level services (provider).

## A. Website security

| # | Finding | Verified state (2026-10-30) | Status |
| --- | --- | --- | --- |
| A1 | X-Frame-Options missing | Live: `X-Frame-Options: SAMEORIGIN` on pages, errors, www | **Stale/false positive** (evidence: curl) |
| A2 | CSP not implemented | Confirmed: policy was Report-Only | **Fixed** — enforced in this change set. The report-only period (from 2026-10-30's deploy) logged **zero violations** (checked via the server-logs workflow). Tightened before enforcing: `unpkg.com` (referenced nowhere) and `gravatar` (feature removed) dropped. `unsafe-inline`/`unsafe-eval` stay on script-src as a **documented requirement** of Livewire/Alpine's inline expressions. Violations keep reporting to `/csp-report` while enforced. Kill-switch: `SECURITY_CSP_REPORT_ONLY=true` reverts without a deploy |
| A3 | nosniff missing | Live: `X-Content-Type-Options: nosniff` everywhere | **Stale/false positive** |
| A4 | "Unmaintained page detected" | The scanner gives no URL. Candidates found: the 7 placeholder subdomain apps (separate products), and two legacy InfoDot views still pulling Unsplash images (`livewire/solution-list`, the auth card background). None is a vulnerability | **Partially identified — follow-up**: owner to pull the exact URL from the scanner's detail view; replace the Unsplash-backed legacy views with local assets as polish |
| A5/A6 | Secure/HttpOnly cookies | Live `Set-Cookie`: session = `secure; httponly; samesite=lax`; XSRF = `secure; samesite=lax` (not HttpOnly **by design** — Livewire/Axios must read it) | **Verified** (R-17 made Secure the production default) |
| A7 | Referrer-Policy | Live: `strict-origin-when-cross-origin` | **Verified** |
| A8 | Tech disclosure | `Server: LiteSpeed` (no version) is set by the shared host and cannot be removed at app level; `X-Powered-By` absent; errors expose nothing (`APP_DEBUG=false`) | **Mitigated/accepted** — residual: product name only |

## B. Email security (both confirmed; owner applies in cPanel → Zone Editor)

Current: SPF `v=spf1 +a +mx +ip4:198.38.92.233 include:spf.mysecurecloudhost.com ~all`;
DMARC `v=DMARC1; p=none;` (no rua — reports currently go nowhere); DKIM present and aligned
(`default` selector, rsa-2048); MX = the host itself. The only legitimate sender is the cPanel
server (`no-reply@infodot.co.za` via SMTP), fully covered by the record's own mechanisms.

1. **Now** — replace the SPF TXT with (softfail → fail; senders unchanged):
   `v=spf1 a mx ip4:198.38.92.233 include:spf.mysecurecloudhost.com -all`
2. **Now** — create a `dmarc@infodot.co.za` mailbox (or forwarder) and replace `_dmarc` TXT with:
   `v=DMARC1; p=none; rua=mailto:dmarc@infodot.co.za; fo=1`
3. **After ~2 weeks of clean reports** — move to `p=quarantine; pct=100` (same record otherwise).
4. **After a further clean period** — `p=reject`.

Transactional mail keeps working at every step because the host is the only sender and stays
authorised; the policy only hardens what happens to forgeries. Verify each change with
`dig TXT infodot.co.za` / `dig TXT _dmarc.infodot.co.za` and a password-reset test email.

## C. Network exposure (shared cPanel — service config is the provider's)

| Service | Verified | Call |
| --- | --- | --- |
| HTTP :80 | Serves only `301 → https` on apex and www | **Required, correct** |
| HTTPS :443 | The product | **Required** |
| SSH :22 | Banner `OpenSSH_9.9`; deploys already use key auth (`deploy_key`) | **Required** for deploys. Provider ticket: confirm the build is ≥9.9p2 or carries backports for **CVE-2025-26466** (pre-auth DoS; fixed 9.9p2) — CVE-2025-26465 is client-side and not a server exposure. Ask whether per-account source restriction and password-auth-off are available. Banner alone does not prove exploitability (patch level not shown) |
| FTP :21 | `Pure-FTPd [TLS]` answering | **Owner decision**: nothing in the Dot workflow uses FTP (deploys are SSH). Ask the provider to disable FTP for the account, or at minimum enforce TLS-only logins. Until then: ensure no FTP accounts exist beyond the main one (cPanel → FTP Accounts) |

No lockout risk from any of the above: all changes are provider-side requests; cPanel web access
is the fallback channel and is untouched.

## D. DNS

| Finding | Verified | Action |
| --- | --- | --- |
| **Domain expiry** | **`infodot.co.za` expires 2026-11-27 — 28 days away** (registry whois) | **URGENT, owner, today**: renew at the registrar, multi-year, auto-renew on, confirm billing contact. Everything else in this report is moot if the domain lapses |
| DNSSEC absent | Confirmed (no DS records) | Owner: cPanel → Zone Editor → DNSSEC (if mysecurecloudhost exposes it) → generate keys → add the DS record at the registrar. Do it outside business hours; a wrong DS breaks all resolution. If the panel has no DNSSEC option, record as provider-blocked |
| CAA absent | Confirmed | Owner: add three CAA records in Zone Editor — `0 issue "letsencrypt.org"` (the live cert's CA, verified), `0 issue "sectigo.com"` (cPanel AutoSSL's fallback CA — keep or renewals may fail), `0 iodef "mailto:privacy@infodot.co.za"` |
| MX/records review | MX = apex (host mail), NS = mysecurecloudhost ×4; ~27 app subdomains inventoried from `config/ecosystem.php`, all resolving to the same host — no dangling CNAMEs found, takeover risk low | **Verified**; placeholder subdomains are a product-polish item, not a security one |

## E. Encryption / HTTPS

Verified live: HTTP→HTTPS 301 on apex and www (the scan's "redirect missing" contradicts its own
"redirects work" line — live testing settles it: redirects work); HSTS `max-age=15552000`
(180 days) present; certificate Let's Encrypt, valid to 2026-12-01, renewed by AutoSSL; no mixed
content (an enforced CSP would now also surface any).

**includeSubDomains and preload are deliberately NOT set**: ~27 subdomains host separate Dot
apps. One HSTS mistake at the apex with includeSubDomains bricks every one of them for 180 days
in visitors' browsers. Revisit only after the fleet-wide header rollout (below) has every
subdomain stable on HTTPS with its own HSTS. Preload is a one-way door and is not pursued.

## F. Application sweep (brief §§2–6) — standing evidence, re-verified where cheap

Tenant isolation, IDOR, uploads, mass assignment, rate limits, secrets (including full git
history), dependency advisories: all covered by the R-15..R-17 work and test suites (1,331 tests
green incl. permission matrices both directions; advisories gate fail-closed; GitGuardian on every
PR; git-history secret scan clean on 2026-10-30 — details in `R17_LAUNCH_READINESS.md`). No new
application vulnerabilities surfaced in this pass. `APP_DEBUG=false` in production (verified by
the Livewire-script behaviour and clean error pages).

## G. New finding from this exercise (not in the scan)

**The ~26 other Dot apps on subdomains serve no security headers at all** (verified on memory,
doc, dopemine, central, pres, tutor: no X-Frame-Options, no nosniff, no HSTS). InfoDot's
middleware is InfoDot-only. This is the single biggest domain-wide hardening win left and almost
certainly part of the scanner's grade. Follow-up (one-app-at-a-time rule): port the
`SecurityHeaders` middleware (headers only — no CSP until each app has had its own report-only
period) across the fleet. Owner priority call.

## H. Register summary

| Finding | Severity | Status | Owner |
| --- | --- | --- | --- |
| Domain expires 2026-11-27 | **Critical (availability)** | Open | **Owner — today** |
| CSP report-only | Medium | **Fixed** (this PR; deploy to take effect) | — |
| SPF ~all | Medium | Open — record above | Owner (5 min) |
| DMARC p=none, no rua | Medium | Open — staged records above | Owner (5 min + 2-week window) |
| CAA absent | Low | Open — records above | Owner (5 min) |
| DNSSEC absent | Low | Open — panel-dependent | Owner/provider |
| OpenSSH 9.9 patch level | Medium (DoS only) | Open — provider ticket | Provider |
| FTP exposed | Low–Medium | Open — disable or TLS-only | Owner/provider |
| X-Frame/nosniff/Referrer/HSTS/redirect/cookies | — | **Stale or verified fine** (evidence above) | — |
| Server product banner | Informational | Accepted | — |
| "Unmaintained page" | Low | Follow-up — needs the scanner's URL | Owner |
| Subdomain apps headerless | Medium (fleet) | Follow-up — fleet rollout | Engineering |

**Honest close:** no security rating is promised. What changed measurably: CSP goes from
advisory to enforced with a tighter allowlist; the registers above turn every remaining scanner
point into a specific owner/provider action with exact values. Re-run the external scan after
the DNS records land and the CSP deploy — and note the two long-standing blockers (restore
drill, Paystack test) are unaffected by this exercise and still gate launch.
