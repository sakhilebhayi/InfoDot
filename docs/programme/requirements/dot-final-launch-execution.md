# R-22 — Owner brief (2026-10-30), stored verbatim (formatting condensed, content complete)

# DOT ECOSYSTEM — FINAL PRODUCTION COMPLETION & LAUNCH EXECUTION PROMPT

## Mission

We are at the final stage of completing the Dot Ecosystem enterprise-readiness programme. The central identity provider, CSP enforcement, security.txt, vulnerability disclosure policy, registration closure, legacy token-URL removal, development seeders, and enterprise completion documentation have already been implemented, deployed and live-verified. Do not revisit or rebuild completed work unnecessarily.

There are five remaining priorities. Your responsibility is to coordinate and execute them in the correct order, complete all engineering work that can be performed, identify actions requiring my direct involvement, and verify every result.

**The objective is to eliminate the remaining launch blockers, complete the SSO migration, secure all deployed platforms, and establish a verified production-ready ecosystem.** Do not merely produce another audit or task list. Execute the work.

# PRIORITY 1 — RENEW INFODOT.CO.ZA (Critical deadline: 27 November 2026)

Registrar-side action requiring the domain owner. Responsibilities: inspect the registration and document the registrar; verify the actual expiration date; confirm the renewal process, period, cost; check auto-renew; identify billing/ownership verification requirements; provide exact renewal steps; verify renewal after registrar confirmation; record the new expiry and evidence. Do not claim renewal without registrar confirmation. Do not change nameservers, DNS or ownership unless explicitly required and approved. Acceptance: renewed, registrar-confirmed, new expiry verified, evidence documented, reminders configured where possible. If direct registrar access is unavailable, prepare everything possible and identify the exact action the owner must complete.

# PRIORITY 2 — COMPLETE DOT IDENTITY RELYING-PARTY CLIENT & DOT.CENTRAL PILOT (Critical engineering dependency)

2.1 Start with the committed implementation specification: `packages/dot-sso-client/README.md` (exact missing classes and requirements) and `docs/programme/` (briefs, reports, architecture decisions, security requirements, prior records). Treat committed documents as the authoritative baseline. Do not invent a replacement architecture. Use Laravel Boost to understand structure and integration points.

2.2 Complete the relying-party client package: every missing class in the README — sign-in initiation, authorization callback handling, state validation, PKCE S256, OIDC nonce validation, ID-token signature verification, issuer/audience validation, secure token exchange, user provisioning, immutable central identity mapping, local session establishment, local authentication interception, session revocation, back-channel logout reception, error handling, security event logging. Use the existing provider and its committed protocol decisions; no custom protocol. Security: exact registered redirect URI matching; no open redirects; no tokens in URL parameters; no browser-readable refresh tokens; no unsigned/incorrectly signed tokens; no expired or replayed authorization codes; no cross-client token substitution; no bypass through legacy routes; no trusting client-supplied user identifiers; no privilege escalation through token claims; secure local sessions; secure key and client-secret management.

2.3 Complete Dot.Central as the pilot: full journey (visit → redirect to InfoDot → authenticate → registered callback → validate → provision/map local user → secure local session → platform authorization enforced → permitted functionality → logout and revocation). Test first-time provisioning AND existing account linking. Authentication is not authorization.

2.4 Platform registration procedure: after tests pass, run `php artisan sso:platform register central` on the InfoDot host; capture credentials securely; transfer via approved secure configuration; store in environment variables. Never commit credentials, print secrets into persistent logs, place secrets in browser code, send secrets through public URLs, or reuse a client secret across applications. Confirm registration, credentials, callbacks and token configuration match on both systems.

2.5 Pilot acceptance tests: login through InfoDot; registration through InfoDot; existing user mapping; new user provisioning; secure callback; PKCE; state and nonce validation; token signature verification; audience validation; tenant isolation; platform-specific permissions; logout; back-channel revocation; invalid token rejection; replay protection; session expiry. Only after all pass may Dot.Central be declared the successful pilot. **Do not start the full migration waves before the pilot is proven.**

# PRIORITY 3 — COMPLETE R-17 LAUNCH BLOCKERS (owner involvement; prepare and automate as much as possible first)

3.1 MySQL Backup Restore Drill: identify latest production backup; verify integrity and completeness; prepare an isolated restoration environment; restore; verify structure, record counts, critical relationships, indexes, constraints, migrations; validate application connectivity; representative queries; confirm sensitive information remains protected; record duration. Do not restore over live production. Document RPO, RTO, actual backup age, restoration time, missing/corrupted data, procedure, limitations. Verify encryption, access control, retention. Acceptance: a successful isolated restoration demonstrated with evidence — a successful backup alone does not count. Prepare commands, access requirements and verification checklist for an efficient host-side operation.

3.2 Live Paystack Payment Verification: real production payment test with an authorised test account and controlled amount; coordinate the payment window with the owner first. Verify checkout creation, amount, currency, customer association, success/failed/cancelled paths, webhook delivery and signature verification, duplicate webhook protection, subscription activation and status changes, billing records, receipts, reconciliation. Server-side verification with Paystack, never browser redirects or client success messages. Webhook secrets private; idempotency enforced; duplicates cannot create duplicate subscriptions/payments; failed transactions do not activate entitlements; payment records not user-manipulable. Do not fabricate successful payment records or use mock transactions as production verification. Acceptance: a real authorised transaction confirmed by Paystack and reconciled with internal billing records; document reference, amount, result, refund/rollback process.

# PRIORITY 4 — COMPLETE R-18 DNS AND HOSTING PROVIDER SECURITY (use the R-18 register as source of truth; do not guess or invent DNS records)

4.1 DMARC: legitimate `rua=` aggregate reporting destination; correct configuration; SPF/DKIM alignment verification; report review; migrate away from `p=none` towards `p=quarantine` and ultimately `p=reject` — without jumping straight to enforcement; confirm transactional email keeps working; avoid invalid or duplicate SPF records.
4.2 CAA: exact approved values from the register; correct syntax; authorised CAs; subdomain coverage; must not break existing certificate issuance/renewal.
4.3 DNSSEC: inspect DNS architecture; confirm provider support; prepare configuration; apply records and DS; verify the full chain with independent tools; confirm normal resolution; monitor validation failures; do not change nameservers or publish incorrect DS records.
4.4 OpenSSH and FTP tickets: OpenSSH — installed version, vendor patches, configuration, authentication methods, admin access and root login restrictions, firewall exposure; resolve via provider-approved updates. FTP — disable if unneeded; otherwise SFTP; verify no disruption to deployment/administrative workflows.
Acceptance: verified configuration, evidence, provider confirmation where applicable, no service disruption, updated R-18 register.

# PRIORITY 5 — FLEET-WIDE SECURITY HEADERS AND SSO MIGRATION

Current report: 16 of 19 deployed platforms without security headers; all platforms other than Dot.Central awaiting SSO migration. Pair security-header remediation and SSO migration into a single controlled deployment per platform.

5.1 Establish the authoritative platform inventory: confirm all 19 deployed platforms, actual domains, hosting, frameworks/versions, existing authentication, existing headers, SSO readiness, deployment dependencies. Do not assume the previous count; record newly deployed, retired or renamed platforms.
5.2 Security header standardisation: per platform — Content-Security-Policy, Strict-Transport-Security, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, frame protection, cache-control. Use InfoDot's implemented CSP/hardening as the baseline, adapted per platform's dependencies; do not blindly copy a CSP that breaks functionality. Test Livewire, Alpine, JS libraries, APIs, WebSockets, integrations, uploads, authentication callbacks. Verify against actual production HTTP responses.
5.3 Platform-by-platform SSO migration after the pilot: inspect existing auth; identify local users; implement the reusable client; configure registered client and exact redirect URIs; map users safely; secure local sessions; remove independent registration and login; central logout and revocation; preserve platform permissions and tenant isolation; apply and verify headers; automated and manual regression; controlled rollout; live verification. One platform, one verified deployment; individual rollback plans; do not proceed to the next platform until the current one passes its gate.
5.4 Maintain a live migration matrix over the actual inventory (platform, domain, headers, SSO, legacy login removed, regression, status).

# PRIORITY 6 — RETIRE LEGACY AUTHENTICATION INFRASTRUCTURE (only after the last platform migration is verified)

Scheduled: `/auth/ecosystem` handoffs; InfoDot launch API; InfoDot redeem API; obsolete token exchange; remaining platform-local login/registration endpoints. Process: identify consumers; confirm all platforms migrated; confirm no production dependency; monitor usage and logs; disable in a controlled manner; remove obsolete code and credentials; update documentation; verify no bypass remains. One authentication authority: InfoDot.

# PRIORITY 7 — CSP ENFORCEMENT MONITORING

Zero `csp.violation` since enforcement. Continue monitoring 24–48 hours: logs, browser reports, Livewire, Alpine, integrations, public and authenticated pages. Do not reintroduce unsafe directives to silence violations. On violations: identify source, legitimate vs malicious, fix dependency or policy, regression-test, re-verify. `SECURITY_CSP_REPORT_ONLY=true` stays an emergency rollback, never the permanent configuration.

# EXECUTION MANAGEMENT

A. Two tracks. Track 1 engineering (SSO client, pilot, migrations, headers, regression, docs, automated verification, DNS preparation, ticket preparation, drill preparation, Paystack preparation). Track 2 owner-dependent (registrar payment/renewal, provider approval, DNS authorisation, restore approval, live payment, anything needing unsupplied credentials). Do not pretend to complete owner tasks without confirmation. For each owner task provide: exact action, why, access needed, estimated time, risk, verification method. Do not re-ask for information already in the repository or documentation.

B. Order: 1 domain renewal; 2 client + pilot; 3 restore drill + Paystack; 4 DNS/provider; 5 fleet headers + SSO; 6 legacy retirement; 7 final CSP observation. Engineering preparation may run in parallel where safe. Client + pilot before migration waves. Legacy auth remains only as a controlled transition until the last platform migrates.

C. Verification gates — statuses: NOT STARTED / IN PROGRESS / BLOCKED — OWNER ACTION REQUIRED / BLOCKED — PROVIDER ACTION REQUIRED / IMPLEMENTED — NOT VERIFIED / VERIFIED — READY FOR DEPLOYMENT / DEPLOYED — LIVE VERIFIED / COMPLETE. Never COMPLETE when only code has been written.

# FINAL ACCEPTANCE CRITERIA

Identity: client package fully implemented; pilot passes; all 19 platforms accounted for; all use InfoDot authentication; no independent registrations or logins; tenant isolation verified; legacy endpoints retired safely. Security: verified headers on all platforms; CSP enforced; no unresolved critical SSO vulnerabilities; tokens/callbacks/sessions pass security testing; DNS configured and verified; OpenSSH/FTP resolved or formally mitigated. Reliability: restoration succeeds; RPO/RTO documented; live Paystack verified; webhooks and subscriptions reconcile. Operations: renewal confirmed; provider tasks closed; migrations documented; rollbacks exist; monitoring active.

# FINAL DELIVERABLE — DOT ECOSYSTEM COMPLETION REPORT

One final report: overall status; renewal evidence; client implementation; pilot results; 19-platform matrix; header verification; legacy retirement evidence; restore evidence; Paystack evidence; DNS evidence; provider tickets; CSP monitoring; outstanding owner actions; residual risks; final launch recommendation. Distinguish code implementation, successful deployment, independent verification, owner/provider confirmation.

## FINAL DIRECTIVE

Do not create another roadmap describing work that needs to happen. Execute the remaining programme. Start with the domain renewal requirements and the Relying Party Client README. Inspect the committed implementation briefs, establish the exact dependencies, and begin completing the SSO client package and Dot.Central pilot. Do not waste time re-auditing completed features unless verification identifies a genuine regression. **The target is a fully operational, centrally authenticated, security-hardened Dot Ecosystem with every deployed platform migrated, every launch blocker verified, and every owner-dependent action clearly closed.**
