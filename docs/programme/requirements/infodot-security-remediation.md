# R-18 — Security vulnerability remediation and hardening

Owner brief, received 2026-10-30 and stored verbatim below, driven by an external security scan of
the same date (rating C, 549/950). Scope: investigate every finding against the real
infrastructure, fix what is fixable from here, hand the registrar/hosting items to the owner with
exact values, and prove each outcome. The remediation register and report live in
`docs/programme/R18_SECURITY_REMEDIATION.md`.

---

# INFODOT — SECURITY VULNERABILITY REMEDIATION & HARDENING MASTER PROMPT

## OBJECTIVE

InfoDot has undergone additional external security assessments using independent security-scanning platforms. Although previous security improvements have been implemented, further vulnerabilities and configuration weaknesses have been identified.

Your task is to conduct a comprehensive security remediation exercise across the entire InfoDot ecosystem and hosting environment, investigate every finding in the supplied security assessment, resolve all confirmed vulnerabilities, and independently verify the results.

**This is a security remediation assignment, not another general security audit.** Do not simply generate a report, recommend changes, or mark findings as resolved. Investigate the actual infrastructure, application code, DNS, server configuration, email configuration, network exposure, and deployment environment. Implement the necessary fixes wherever you have authorised access.

The ultimate objective is to make InfoDot demonstrably secure, resilient, and suitable for enterprise and commercial production use.

## 1. SOURCE SECURITY ASSESSMENT

Use the supplied security assessment as the initial remediation baseline.

The assessment, dated 30 October 2026, reports:

- Security rating: C
- Score: 549/950
- Website Security: 7/11 passed
- Email: 4/6 passed
- Network: 0/4 passed
- DNS: 3/6 passed
- Encryption: 14/18 passed
- Data Leakage: 2/2 passed
- IP/Domain Reputation: 13/13 passed
- Vulnerability Management: 4/5 passed

Investigate the following findings individually.

### A. Website security

1. X-Frame-Options is not configured as DENY or SAMEORIGIN.
2. Content Security Policy (CSP) is not implemented.
3. X-Content-Type-Options is not configured as nosniff.
4. An apparently unmaintained page has been detected.
5. Verify that Secure cookies are consistently enforced.
6. Verify that HttpOnly cookies are consistently enforced.
7. Verify that the Referrer-Policy is configured safely.
8. Confirm that server technology and version information are not unnecessarily exposed through HTTP headers or error responses.

Do not blindly add headers without understanding the application's requirements. For example, determine whether legitimate embedding, payment providers, authentication providers, Livewire functionality, WebSockets, analytics, or other trusted integrations require specific CSP permissions.

Implement a restrictive CSP based on actual application dependencies. Prefer nonce-based or hash-based script policies where practical. Avoid using unsafe-inline or unsafe-eval unless a documented technical requirement makes them unavoidable.

### B. Email security

Investigate and remediate:

1. DMARC currently uses p=none.
2. SPF currently uses the permissive ~all mechanism.

Requirements:

- Inspect the complete existing DNS records.
- Identify every legitimate service authorised to send email on behalf of InfoDot.
- Verify SPF alignment, DMARC alignment, DKIM signing, and all relevant third-party sending services.
- Analyse DMARC reports before enforcing stricter policies.
- Progress towards p=quarantine and ultimately p=reject when legitimate sending sources have been verified.
- Replace permissive SPF configuration with an appropriate restrictive policy once all authorised senders are accounted for.
- Avoid duplicate SPF records and invalid DNS configurations.
- Ensure transactional emails, password resets, notifications, verification emails, and other legitimate messages continue working.

Never introduce an SPF or DMARC configuration that accidentally blocks legitimate InfoDot communications.

### C. Network security

The assessment reports the following externally visible services:

- FTP
- SSH
- HTTP
- HTTPS

Investigate each exposed port.

Requirements:

- Determine which services are genuinely required.
- Disable standard FTP if it is unnecessary and migrate legitimate file-transfer requirements to SFTP or another secure alternative.
- Restrict SSH access to authorised administrators and trusted sources wherever practical.
- Disable password-based SSH authentication where key-based authentication is supported and tested.
- Disable root SSH login where appropriate.
- Review SSH protocol and cryptographic configuration.
- Confirm the actual installed OpenSSH version and patch level.
- Investigate the reported OpenSSH 9.9 vulnerability warning against the exact installed build and applicable CVEs.
- Patch or upgrade vulnerable software using a supported version compatible with the hosting environment.
- Confirm firewall rules and exposed services.
- Restrict administrative interfaces and unnecessary public ports.
- Ensure HTTP only serves the necessary redirect to HTTPS.

Do not assume that an open port is automatically a vulnerability. Determine whether the service is necessary, appropriately configured, and protected.

**Do not lock yourself out of SSH, cPanel, deployment access, or production infrastructure while applying changes.** Validate alternative administrative access before modifying remote access rules.

### D. DNS security

Investigate:

1. Domain expiry warning.
2. DNSSEC not enabled.
3. CAA records not configured.
4. Existing MX and mail-related DNS records.

Requirements:

- Verify the actual domain registration expiry date with the registrar.
- Confirm renewal arrangements and ownership.
- Determine whether DNSSEC is supported by the DNS provider and registrar.
- Implement DNSSEC carefully, validating the DNS chain and avoiding DNS resolution outages.
- Configure appropriate CAA records to restrict certificate issuance to authorised certificate authorities.
- Review A, AAAA, CNAME, MX, TXT, NS, and other relevant records.
- Identify unused, outdated, dangling, or unnecessarily exposed subdomains.
- Check for subdomain takeover risks.
- Document all DNS changes and their purpose.

Do not change nameservers or DNS records without understanding the current hosting and email architecture.

### E. Encryption and HTTPS

The assessment reports:

- HSTS is not enforced.
- HTTPS redirect support is flagged as missing.
- HSTS includeSubDomains is missing.
- The domain is not on the HSTS preload list.

Although the assessment also reports that HTTP requests redirect to HTTPS, investigate the discrepancy independently.

Requirements:

- Verify HTTP-to-HTTPS redirects from all relevant entry points.
- Ensure no redirect loops or insecure intermediate redirects exist.
- Configure HSTS with an appropriate max-age.
- Evaluate includeSubDomains only after confirming that every affected subdomain supports HTTPS.
- Evaluate preload eligibility separately; do not submit the domain for preload until every requirement is satisfied and the consequences are understood.
- Verify certificate validity, renewal automation, certificate chain, supported TLS versions, and cipher suites.
- Eliminate mixed-content issues.
- Ensure sensitive information is never transmitted over unencrypted connections.

Do not apply HSTS preload or includeSubDomains recklessly. A configuration mistake can make subdomains inaccessible.

### F. Vulnerability management

The scan reports potential vulnerabilities associated with OpenSSH 9.9.

Requirements:

- Identify the exact server, service, version, package build, operating system, and patch status.
- Map the finding to relevant CVE identifiers.
- Determine whether the affected configuration and components are actually exposed.
- Review vendor security advisories and available patches.
- Patch, mitigate, or document any finding that cannot immediately be resolved.
- Verify the resulting version and configuration.

Do not assume that a version warning proves exploitability. Equally, do not dismiss a vulnerability because the scanner cannot establish the exact configuration.

## 2. INVESTIGATE THE ENTIRE INFODOT ATTACK SURFACE

Do not limit the remediation to the findings listed in the assessment.

Conduct an additional vulnerability assessment covering:

- Public-facing website and landing pages.
- Authentication and registration.
- Password resets and email verification.
- User profiles and business profiles.
- Questions, answers, and solutions.
- Attachments and file uploads.
- User mentions and recommendations.
- Solution validation and implementation feedback.
- Collaborative solution improvements and version history.
- Search and duplicate detection.
- Notifications.
- User-generated content.
- AI functionality and integrations.
- Dot.Brains, Dot.Memory, and Dot.Dopemine integrations.
- Rewards and user scoring.
- Subscription and billing functionality.
- Administrative dashboards.
- API endpoints and webhooks.
- Background jobs, queues, scheduled tasks, and workers.
- Database access and tenant isolation.
- File storage and download endpoints.
- Third-party integrations and dependencies.

Pay particular attention to:

- Broken access control and IDOR.
- Cross-tenant data exposure.
- Privilege escalation.
- Authentication bypass.
- Session fixation and hijacking.
- CSRF.
- XSS, including stored and reflected XSS.
- SQL injection.
- Mass assignment.
- Insecure direct file access.
- Unsafe file uploads.
- SSRF.
- Insecure deserialisation.
- Business logic abuse.
- Rate-limit bypass.
- API authorisation failures.
- Exposed secrets and credentials.
- Vulnerable dependencies.
- Insecure configuration.
- Information disclosure.

Use an appropriate risk-based testing approach aligned with OWASP ASVS and OWASP Top 10.

## 3. LARAVEL APPLICATION SECURITY

Use Laravel Boost to understand and inspect the actual codebase.

Inspect:

- Routes and middleware.
- Controllers and Livewire components.
- Models and relationships.
- Policies and gates.
- Form requests and validation.
- Authentication and authorisation.
- Database migrations and constraints.
- API resources and responses.
- Events, listeners, jobs, and queues.
- File handling.
- Configuration and environment variables.
- Third-party packages and dependencies.

Implement and verify:

- Server-side authorisation for every protected action.
- Resource ownership checks.
- Tenant-aware database queries.
- Protection against cross-company record access.
- Protection against mass assignment and field tampering.
- Parameterised queries and safe database operations.
- Strict input validation.
- Context-appropriate output escaping.
- CSRF protection.
- Secure password hashing using supported Laravel password-hashing mechanisms.
- Secure session configuration.
- Secure, HttpOnly, and appropriate SameSite cookie attributes.
- Login, registration, password-reset, and API rate limits.
- Bot and automated-abuse protection.
- Secure upload validation, storage, and download authorisation.
- Safe error handling without exposing internal details.
- Minimal API responses that disclose only necessary information.

Never rely on frontend restrictions as a security boundary.

## 4. SECRET AND CREDENTIAL REMEDIATION

Conduct a complete secret-exposure assessment.

Inspect:

- Current Git-tracked files.
- Git history.
- Configuration files.
- Environment files.
- Deployment scripts.
- CI/CD configuration.
- Logs and debug output.
- Public JavaScript bundles.
- Source maps.
- API responses.
- Exposed repositories and package artefacts.

Look for:

- Database credentials.
- API keys.
- Cloud credentials.
- SMTP credentials.
- OAuth secrets.
- Payment gateway credentials.
- Application keys.
- Private certificates and signing keys.
- Service-role keys.
- Access tokens.

Requirements:

1. Remove exposed secrets from active code.
2. Revoke and rotate compromised credentials.
3. Move secrets into appropriate environment or secret-management facilities.
4. Prevent secrets from entering future commits.
5. Add appropriate secret scanning to CI/CD.
6. Investigate and remediate exposed Git history where necessary.
7. Verify that production secrets are never returned to browsers or unauthorised API clients.

Do not confuse public client identifiers with private credentials. Where a database platform supports public/anonymous keys and Row Level Security, ensure RLS is enabled and correctly enforced. Never expose service-role keys, database passwords, or privileged credentials.

## 5. DATABASE AND DATA PROTECTION

Inspect the database architecture and implement:

- Least-privilege database accounts.
- Proper record-level access controls.
- Tenant isolation.
- Encryption in transit.
- Appropriate encryption at rest.
- Encryption of sensitive application data where justified.
- Safe key management and rotation.
- Secure backups.
- Restricted database network access.
- Proper database constraints and indexes.
- Auditing of sensitive administrative actions.
- Protection against unauthorised exports and bulk extraction.

If Row Level Security is supported by the actual database architecture, verify that policies are correctly implemented and tested. Do not introduce an incompatible database architecture simply to satisfy a scanner checklist.

## 6. FILE UPLOAD AND CONTENT SECURITY

InfoDot allows users to submit questions and solutions with supporting PDFs, images, and videos.

Treat this as a significant attack surface.

Verify:

- Allowed file extensions and MIME types.
- File signature validation.
- Maximum upload sizes.
- Secure random storage names.
- Storage outside the public web root where appropriate.
- Malware scanning where available.
- Protection against executable uploads.
- Safe PDF and document handling.
- Image processing and metadata considerations.
- Video upload restrictions.
- Authorisation on every download.
- Signed or temporary download links where appropriate.
- Protection against path traversal.
- Prevention of unauthorised file replacement or deletion.

Never trust a filename, browser-provided MIME type, or client-side validation.

## 7. SECURITY HEADERS AND BROWSER PROTECTION

Implement and verify an appropriate security-header configuration, including:

- Strict-Transport-Security.
- Content-Security-Policy.
- X-Frame-Options or an appropriate CSP frame-ancestors policy.
- X-Content-Type-Options: nosniff.
- Referrer-Policy.
- Permissions-Policy.
- Appropriate cache-control headers for sensitive responses.

Remove unnecessary technology disclosure headers.

Test the actual response headers on public pages, authenticated pages, API responses, error pages, and relevant subdomains.

## 8. APPLICATION AND INFRASTRUCTURE HARDENING

Review the complete hosting and deployment environment.

Check:

- Operating system updates.
- Web server configuration.
- PHP version and security support.
- Laravel version and security advisories.
- Composer dependencies.
- Node dependencies.
- SSH configuration.
- Firewall configuration.
- Cron jobs and scheduled tasks.
- Queue workers.
- File and directory permissions.
- Backup configuration.
- Log permissions and retention.
- Debug mode.
- Error reporting.
- Administrative access.
- Production environment configuration.

Ensure:

- APP_DEBUG is disabled in production.
- Sensitive configuration is not publicly accessible.
- Directory listing is disabled.
- Unnecessary services are disabled.
- Production dependencies are maintained.
- Deployment permissions follow least privilege.
- Backups are encrypted and restoration is tested.

## 9. SECURITY TESTING AND INDEPENDENT VERIFICATION

Do not mark any vulnerability as resolved merely because a configuration file was changed.

For every finding:

1. Reproduce or independently verify the original issue.
2. Identify its actual root cause.
3. Determine the affected component.
4. Implement the fix.
5. Test the fix.
6. Confirm that the fix has not introduced regressions.
7. Run an independent verification scan.
8. Record evidence.

Use appropriate tools available in the environment, such as:

- Laravel Boost.
- Laravel automated tests.
- Composer security audits.
- Static analysis.
- Secret scanning.
- OWASP ZAP.
- Nmap, where authorised.
- SSL/TLS configuration testing.
- HTTP security-header testing.
- DNS configuration verification.
- Dependency vulnerability scanning.

Only scan systems and infrastructure that are owned by InfoDot or for which explicit authorisation exists. Do not conduct destructive testing, denial-of-service testing, or unauthorised exploitation.

Where an external scanner identifies a false positive, document the evidence supporting that conclusion.

## 10. PRODUCTION SAFETY

Security improvements must not break legitimate InfoDot functionality.

Before modifying production infrastructure:

- Establish the current configuration.
- Identify dependencies.
- Create recoverable backups.
- Prepare rollback procedures.
- Test changes in a staging environment wherever possible.
- Confirm access to an alternative administrative channel.
- Apply changes in controlled stages.
- Verify application health after each significant change.

Do not disable legitimate functionality simply to improve a scanner score.

Do not expose private infrastructure information in public reports.

## 11. REMEDIATION TRACKER

Create a structured remediation register.

For every finding, record:

| Field | Required information |
|---|---|
| Finding | Exact vulnerability or configuration issue |
| Source | External scan or internal assessment |
| Asset | Affected domain, service, application, or component |
| Severity | Critical, High, Medium, Low, or Informational |
| Evidence | Actual observed behaviour or configuration |
| Root cause | Why the issue exists |
| Remediation | What was changed |
| Verification | How the fix was tested |
| Status | Open, In Progress, Fixed, Mitigated, or False Positive |
| Residual risk | Remaining exposure |
| Owner | Responsible party |
| Follow-up | Required monitoring or action |

Prioritise remediation by actual risk and exploitability, not simply by scanner score.

## 12. FINAL ACCEPTANCE CRITERIA

The work is complete only when:

- Every supplied finding has been investigated.
- Every confirmed and remediable vulnerability has been fixed.
- Critical and High findings have no unresolved exposure without an explicitly documented mitigation and risk acceptance.
- External network exposure has been reviewed and justified.
- DNS and email security configurations have been validated.
- HTTPS, TLS, HSTS, and security headers have been independently verified.
- No private credentials or secrets are exposed.
- Application authentication and authorisation have been tested.
- Tenant isolation has been verified.
- File uploads and downloads have been security-tested.
- Automated application tests pass.
- Relevant security scans have been repeated.
- Existing InfoDot functionality remains operational.
- Deployment and rollback procedures are documented.

Do not promise a particular security rating or claim that InfoDot is completely secure. Report measurable improvements and remaining risks honestly.

## 13. FINAL DELIVERABLES

Produce a comprehensive security remediation report containing:

1. Executive security summary.
2. Original security assessment findings.
3. Findings independently confirmed.
4. False positives and their supporting evidence.
5. Vulnerabilities successfully fixed.
6. Exact files, configurations, services, and DNS records changed.
7. Infrastructure changes and outstanding hosting-provider actions.
8. Application-level security improvements.
9. Git secrets and credential remediation results.
10. Test results and independent verification evidence.
11. Before-and-after security configuration comparisons.
12. Outstanding vulnerabilities and residual risks.
13. Rollback procedures.
14. Recommended ongoing security monitoring.
15. Final production-readiness assessment.

Provide a clear remediation status for every finding. Distinguish between fixes implemented directly, changes requiring hosting-provider or registrar access, and changes that require approval.

## FINAL INSTRUCTION

Work systematically and thoroughly. Start by understanding the actual InfoDot codebase, hosting architecture, domain configuration, and supplied security assessment.

Do not rewrite working systems unnecessarily. Do not introduce speculative infrastructure changes. Do not use mock security results, fabricated scan outputs, or placeholder implementations.

**Investigate → Reproduce → Understand → Remediate → Test → Independently Verify → Document.**

The goal is not to make InfoDot look secure on paper. The goal is to make it genuinely harder to compromise, protect its users and business data, and establish a security posture that can withstand independent technical scrutiny.
