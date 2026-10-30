# R-19 — Targeted remediation: CSP hardening, HTTP methods, security disclosure

Owner brief, received 2026-10-30 and stored verbatim below. Scope: remove unsafe CSP directives
where the stack genuinely allows it, restrict object-src, modernise CSP reporting, verify HTTP
OPTIONS handling, publish security.txt (RFC 9116), and verify the login-interface finding — each
against the live site, with regression proof. Report: `docs/programme/R19_CSP_HARDENING.md`.

---

# INFODOT — TARGETED SECURITY REMEDIATION: CSP HARDENING, HTTP METHODS & SECURITY DISCLOSURE

## Mission

We have identified additional security configuration weaknesses on the live InfoDot website:

https://www.infodot.co.za/

Previous security hardening has improved the platform, but the latest independent security assessment has identified outstanding issues involving:

1. Content Security Policy (CSP).
2. Unsafe JavaScript execution permissions.
3. Missing `object-src` restriction.
4. Deprecated CSP reporting configuration.
5. HTTP OPTIONS method detection.
6. Missing `security.txt` disclosure file.
7. Public login interface exposure.

Your task is to investigate, remediate, test, and independently verify every finding.

This must be performed against the actual InfoDot Laravel application and its production hosting configuration.

**Do not simply suppress scanner findings. Fix the underlying configuration or application weaknesses.**

Use Laravel Boost to inspect the codebase, understand dependencies, identify affected routes and components, and implement application-level changes.

Do not rewrite working functionality unnecessarily.

---

# 1. CONTENT SECURITY POLICY — REMOVE UNSAFE DIRECTIVES

## Current findings

The production HTTP Content-Security-Policy header contains:

- `script-src 'unsafe-inline'`
- `script-src 'unsafe-eval'`
- Missing or insufficient `object-src` restriction.
- Deprecated `report-uri` directive.

These directives weaken the browser's protection against cross-site scripting and other injection attacks.

## Required remediation

### A. Eliminate `unsafe-inline`

Investigate every inline JavaScript dependency in InfoDot.

Pay particular attention to:

- Laravel Livewire.
- Alpine.js.
- Blade templates.
- Inline scripts.
- Inline event handlers.
- Dynamically generated scripts.
- Authentication interfaces.
- Password visibility controls.
- Interactive components.
- Public landing pages.
- Question and solution interfaces.
- Third-party scripts.

Refactor inline JavaScript wherever practical.

Where inline scripts are genuinely required, implement CSP nonces or cryptographic hashes. Ensure that nonces are generated securely for each response and applied consistently to the relevant scripts.

Do not replace unsafe inline JavaScript with another unrestricted execution mechanism.

### B. Eliminate `unsafe-eval`

Investigate why the application currently requires `unsafe-eval`.

Identify the actual dependency or component requiring it.

Review:

- Alpine.js expressions.
- Livewire functionality.
- JavaScript libraries.
- Development-only tooling accidentally included in production.
- Third-party scripts.
- Runtime compilation or dynamic evaluation.

Refactor, replace, or correctly configure dependencies to avoid unsafe evaluation.

Do not remove `unsafe-eval` blindly and leave broken functionality behind.

The final production CSP must not contain `unsafe-eval`.

### C. Configure `object-src`

Explicitly configure:

`object-src 'none'`

Verify that legitimate application functionality does not depend on embedded objects, Flash-style plugins, or other object-based content.

Where documents, PDFs, or videos are supported, ensure they continue to work through appropriate HTML elements and secure delivery mechanisms.

### D. Replace deprecated CSP reporting

Investigate the existing `report-uri` configuration.

Implement the modern CSP reporting mechanism using:

- `Reporting-Endpoints`.
- CSP `report-to`.

Configure an appropriate reporting endpoint if the application infrastructure supports it.

Ensure that:

- Reports are received and processed safely.
- Invalid or oversized reports cannot exhaust application resources.
- Sensitive information is not unnecessarily retained.
- Reporting failures do not affect normal application functionality.

Do not introduce a reporting endpoint that accepts unrestricted requests without appropriate protections.

### E. Build a restrictive production CSP

Create a policy based on the actual application dependencies.

Evaluate directives including:

- `default-src`.
- `script-src`.
- `script-src-attr`.
- `style-src`.
- `style-src-attr`.
- `img-src`.
- `font-src`.
- `connect-src`.
- `frame-src`.
- `frame-ancestors`.
- `object-src`.
- `base-uri`.
- `form-action`.
- `upgrade-insecure-requests`.

Requirements:

- Restrict resources to trusted origins.
- Avoid wildcard permissions unless justified.
- Avoid unnecessary third-party dependencies.
- Prevent unauthorised framing.
- Restrict form submission destinations.
- Prevent object embedding.
- Avoid unsafe script execution.
- Maintain compatibility with Livewire, Alpine.js, and other verified dependencies.

If a strict CSP breaks an existing feature, identify and fix the underlying incompatibility rather than weakening the entire policy.

### F. Introduce staged CSP enforcement

Where necessary:

1. Begin with a tested Content-Security-Policy-Report-Only configuration.
2. Capture and investigate legitimate policy violations.
3. Fix application dependencies.
4. Move to an enforced Content-Security-Policy.
5. Verify that the final enforced policy does not contain `unsafe-inline` or `unsafe-eval`.

Do not leave the production website permanently operating in report-only mode.

---

# 2. HTTP OPTIONS METHOD

## Current finding

The external scanner performed an HTTP OPTIONS request against:

https://www.infodot.co.za/

The server returned:

`HTTP 200`

With:

`Allow: GET,HEAD`

The scanner classified this as an informational finding.

## Required investigation

Determine why the OPTIONS request returns HTTP 200 and whether this behaviour is coming from:

- Nginx or Apache.
- Hosting infrastructure.
- Laravel routing.
- Middleware.
- CORS configuration.
- A reverse proxy or CDN.

Inspect the actual HTTP method handling across the application.

Requirements:

1. Determine whether OPTIONS is required for legitimate browser preflight requests.
2. Identify whether any application endpoints unintentionally process unsupported HTTP methods.
3. Ensure that GET and HEAD cannot perform unintended state-changing operations.
4. Verify that POST, PUT, PATCH, and DELETE operations require appropriate authentication, authorisation, and CSRF protection.
5. Ensure CORS only permits explicitly trusted origins, methods, and headers.
6. Ensure unsupported methods are handled appropriately.
7. Confirm that the HTTP Allow header accurately reflects the supported methods for the relevant resource.

Do not disable OPTIONS globally if legitimate CORS preflight requests or integrations require it.

Do not treat an OPTIONS response as a vulnerability by itself. The objective is to ensure that HTTP method handling is intentional, secure, and correctly configured.

Retest the root URL and relevant application endpoints after changes.

---

# 3. SECURITY.TXT — RESPONSIBLE VULNERABILITY DISCLOSURE

## Current finding

The following file is missing:

`https://www.infodot.co.za/.well-known/security.txt`

Implement a standards-compliant security.txt file following RFC 9116.

Create it at:

`public/.well-known/security.txt`

Ensure the production URL returns HTTP 200 and the file has the correct content type.

Include:

- Contact: a legitimate, monitored security contact address.
- Preferred-Languages: languages supported by the security team.
- Canonical: the exact HTTPS security.txt URL.
- Policy: a legitimate vulnerability disclosure policy URL, if one exists.
- A suitable expiry date.

Use real contact details and actual policy URLs. Do not invent an email address or create a false disclosure policy.

If a dedicated security contact has not yet been established, identify this as an outstanding business requirement rather than publishing fabricated information.

The security.txt file must:

- Be publicly accessible.
- Contain valid syntax.
- Have a future expiry date.
- Be renewed before expiry.
- Avoid exposing internal infrastructure information.

Add an appropriate automated reminder or operational process for expiry management.

---

# 4. LOGIN INTERFACE — VERIFY AUTHENTICATION SECURITY

## Current finding

The scanner detected a public login interface on:

https://www.infodot.co.za/solution/view/8

The response contains:

- Email input.
- Password input.
- Remember me checkbox.
- Password visibility toggle.
- Sign-in button.

The presence of a public login interface is not automatically a vulnerability.

However, investigate why a login form is rendered on a solution page and ensure that this behaviour is intentional.

Inspect:

- Public and private solution access.
- Authentication middleware.
- Redirect behaviour.
- Login form submission.
- Session handling.
- Password visibility functionality.
- CSRF protection.
- Rate limiting.
- Authentication error messages.
- Remember-me cookie handling.

Requirements:

1. Publicly accessible content must not expose private business or user information.
2. Protected solutions must require authentication and server-side authorisation.
3. Users must not be able to access another user's private records by changing a URL or record identifier.
4. Authentication must be protected against brute-force attempts.
5. Password reset and account verification must be secure.
6. Session cookies must use Secure, HttpOnly, and appropriate SameSite settings.
7. Login responses must not reveal whether a particular email address has an account.
8. Password visibility controls must remain functional without requiring unsafe CSP directives.

Test the solution page as:

- An unauthenticated visitor.
- An authenticated standard user.
- A user belonging to another business tenant.
- An administrator.

Verify the expected access rules in every scenario.

Do not hide the login interface merely to improve scanner results.

---

# 5. FULL APPLICATION COMPATIBILITY REVIEW

After making these changes, perform a full regression review of all relevant InfoDot functionality.

Use Laravel Boost to inspect and test:

- Public landing pages.
- Registration and authentication.
- User profiles.
- Business profiles.
- Questions.
- Answers and solutions.
- PDF, image, and video attachments.
- Mentions.
- Solution recommendations.
- Implementation confirmations.
- Collaborative improvements.
- Search and duplicate detection.
- Notifications.
- User rewards.
- Dot.Brains integration.
- Dot.Memory integration.
- Dot.Dopemine integration.
- Administrative functions.
- API endpoints.

Pay particular attention to JavaScript interactions that could be affected by CSP enforcement.

Verify:

- Livewire requests and navigation.
- Alpine.js interactions.
- Password visibility toggles.
- Form submissions.
- File uploads.
- Search inputs.
- Modal windows.
- Notifications.
- Any dynamically loaded content.

No existing functionality should silently break.

---

# 6. SECURITY VERIFICATION

After implementation, perform independent verification against the live production domain, where authorised.

Use suitable tools such as:

- Laravel Boost.
- Browser developer tools.
- curl.
- OWASP ZAP.
- CSP evaluators.
- HTTP security-header scanners.
- Browser CSP violation reports.
- Laravel automated tests.

Verify the following.

| Requirement | Expected result |
|---|---|
| `unsafe-inline` in script-src | Absent |
| `unsafe-eval` in script-src | Absent |
| `object-src` | `none` |
| CSP reporting | Modern reporting configuration where supported |
| CSP enforcement | Enforced, not permanently report-only |
| HTTP OPTIONS | Intentional and correctly configured |
| CORS | Restricted to trusted origins |
| security.txt | Publicly accessible and valid |
| Login interface | Authentication and authorisation secure |
| Livewire | Fully functional |
| Alpine.js | Fully functional |
| Public pages | No CSP violations affecting functionality |
| Authenticated pages | No CSP violations affecting functionality |

Test multiple routes, not just the homepage.

Include:

- `/`
- `/login`
- `/register`
- `/solution/view/8`
- Other representative public and authenticated pages.

Use both GET and OPTIONS requests where appropriate.

Check actual response headers, browser console errors, and application logs.

Do not rely on a single scanner's interpretation of the configuration.

---

# 7. IMPLEMENTATION RULES

Follow these rules throughout the assignment:

1. Inspect the actual codebase before changing anything.
2. Use Laravel Boost to understand existing architecture and dependencies.
3. Identify the root cause of each finding.
4. Make minimal, targeted changes.
5. Avoid unnecessary rewrites.
6. Do not weaken existing security controls to preserve compatibility.
7. Do not introduce mock security implementations.
8. Do not hardcode secrets or credentials.
9. Do not disable security scanners or suppress findings without justification.
10. Do not claim a vulnerability is fixed until it has been independently tested.
11. Preserve the existing InfoDot design and user experience.
12. Test changes in staging before production deployment wherever possible.
13. Back up relevant configurations before modifying production infrastructure.
14. Provide rollback instructions.
15. Clearly identify any changes that require hosting-provider or administrator access.

If a change cannot be safely implemented because access or information is missing, explain precisely what is required.

---

# 8. FINAL DELIVERABLE

Produce a security remediation report containing:

1. Original findings.
2. Root cause of each finding.
3. Changes implemented.
4. Exact files and configurations modified.
5. CSP before-and-after comparison.
6. HTTP OPTIONS and CORS verification.
7. security.txt implementation and validation.
8. Authentication security test results.
9. Livewire and Alpine.js compatibility results.
10. Automated test results.
11. Independent security scan results.
12. Outstanding issues.
13. Infrastructure or hosting-provider actions still required.
14. Production deployment and rollback instructions.

For every finding, use one of these statuses:

- FIXED AND VERIFIED.
- FIXED — PENDING EXTERNAL VERIFICATION.
- MITIGATED.
- FALSE POSITIVE — EVIDENCE PROVIDED.
- OUTSTANDING — ACCESS OR APPROVAL REQUIRED.

## Definition of done

The task is only complete when the identified weaknesses have been addressed at their actual source, the production configuration has been verified, the affected InfoDot functionality continues working, and evidence is provided for every claimed fix.

**Do not optimise for a security scanner score. Optimise for genuine security.**

Investigate → Reproduce → Remediate → Regression Test → Independently Verify → Document.
