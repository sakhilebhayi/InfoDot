# R-20 — Owner brief (2026-10-30), stored verbatim

> Received 2026-10-30. The owner's instruction with it: "before we deploy, lets do this then
> merge again and deploy everything" — i.e. the pending R-19 deploy (PR #179) waits, this work
> happens, then everything merges and deploys together.

---

# DOT ECOSYSTEM — UNIFIED IDENTITY, SINGLE SIGN-ON & CENTRAL AUTHENTICATION MASTER IMPLEMENTATION PROMPT

## 1. THE VISION

We are consolidating authentication across the entire Dot ecosystem.

**InfoDot must become the single, central identity provider and authentication authority for every Dot ecosystem platform.**

Users must only be able to register, authenticate, recover accounts and manage their central identity through InfoDot.

No other Dot platform may independently create user accounts, maintain separate passwords, or provide its own login or registration system.

Every platform must rely on InfoDot to establish and verify the identity of the person accessing it.

This must apply to every existing and future Dot ecosystem platform.

The user's experience should be simple:

- A user visits any Dot platform.
- If they are not authenticated, they are redirected to InfoDot to sign in.
- If they do not have an account, they register through InfoDot.
- Once authentication succeeds, they are securely redirected back to the exact platform and page they originally wanted to access.
- The destination platform validates the authentication response and establishes its own secure application session.
- The user can navigate between authorised Dot platforms without repeatedly entering their credentials.
- Logging out centrally must invalidate the user's access across the ecosystem according to the defined session and token revocation strategy.

**There must be one identity, one registration system, one authentication authority and one centrally managed credential system.**

This is an architectural change to authentication, not a domain consolidation.

---

## 2. DOT ECOSYSTEM PLATFORMS

Implement central authentication across every existing Dot ecosystem platform.

The following platforms must be included in the discovery and implementation process:

- Dot.Analytics
- Dot.Pulse
- Dot.Plug
- Dot.Mines
- Dot.Notify
- Dot.Billing
- Dot.Charts
- Dot.Farms
- Dot.HR
- Dot.Memory
- Dot.Brain
- Dot.Dopemine
- Dot.Emall
- Dot.Ehail
- Dot.Agents
- Dot.Auction
- Dot.Central
- Dot.Projects
- Dot.Tasks
- Dot.Design
- Dot.Finance
- Dot.Docs

Also discover any additional existing Dot platforms, applications, APIs, subdomains, administrative applications or mobile clients that are not listed above.

Do not assume this list represents the complete deployed ecosystem.

Create a central platform registry that records:

- Platform name.
- Existing domain.
- Application identifier.
- Environment.
- Registered redirect URIs.
- Permitted authentication flows.
- Token audiences.
- Required scopes.
- Platform status.
- Integration health.
- Security configuration.

Every platform must be explicitly registered and approved before it can use the central identity provider.

New Dot platforms must integrate with the central identity service from the beginning.

---

## 3. DOMAIN AND APPLICATION PRESERVATION

This requirement is critical.

**All existing platform domains must remain unchanged.**

For example:

- Dot.Mines retains its existing domain.
- Dot.Analytics retains its existing domain.
- Dot.Brain retains its existing domain.
- Dot.Docs retains its existing domain.
- Every other platform retains its existing domain.

Do not merge the applications into InfoDot.

Do not redirect users permanently to the InfoDot website for normal platform usage.

Do not change the platforms' existing branding, routes, functionality, databases or application architecture unnecessarily.

Only the authentication and identity-management architecture must be centralised.

InfoDot handles identity and authentication. Each platform remains responsible for its own application functionality and authorisation.

---

## 4. CENTRAL IDENTITY PROVIDER ARCHITECTURE

Design and implement a secure central identity provider operated by InfoDot.

Use established authentication standards rather than inventing a proprietary authentication protocol.

### Preferred protocol

Evaluate and implement:

- OpenID Connect (OIDC) for authentication.
- OAuth 2.0 Authorization Code Flow.
- PKCE using S256.
- Short-lived, signed identity and access tokens.
- Refresh-token rotation where applicable.
- Centralised session management.
- Secure token revocation.
- Registered client applications.

Use a mature, maintained OIDC implementation or a properly configured identity-provider framework compatible with the existing Laravel architecture.

Do not build a custom OAuth/OIDC implementation from scratch unless there is a compelling, documented technical reason.

InfoDot must act as the trusted OpenID Provider (OP).

Every Dot platform must act as a registered OpenID Connect client (Relying Party).

### Central identity endpoints

Establish the necessary standard OIDC endpoints, including:

- Discovery metadata.
- Authorization endpoint.
- Token endpoint.
- UserInfo endpoint where required.
- JWKS public signing-key endpoint.
- Revocation endpoint.
- Logout endpoint.
- Session management or logout notification mechanisms where supported.

Use standard endpoint discovery and metadata rather than hardcoding inconsistent authentication configurations into every platform.

All endpoints must use HTTPS.

---

## 5. USER REGISTRATION — INFODOT ONLY

Remove independent account registration from every other Dot platform.

When a user selects Create Account on any Dot platform:

1. Identify the originating registered platform.
2. Preserve the requested destination URL safely.
3. Redirect the user to the InfoDot registration experience.
4. Allow the user to create their central Dot identity through InfoDot.
5. Verify their email address.
6. Apply the appropriate account security checks.
7. Complete the registration process.
8. Return the user to the original platform through the registered authentication flow.
9. Establish a secure local application session on the destination platform.
10. Continue to the originally requested page.

A user must not be able to register directly through another Dot platform.

All account creation, identity verification, password management and recovery must be controlled by InfoDot.

### Registration experience

The InfoDot registration experience must support:

- Secure email verification.
- Strong password requirements.
- Secure password hashing.
- Duplicate account detection.
- Account recovery.
- Abuse prevention.
- Rate limiting.
- Bot protection.
- Optional multi-factor authentication.
- Appropriate privacy and consent management.

Preserve the original platform and requested destination throughout the registration process without trusting arbitrary redirect parameters.

---

## 6. USER LOGIN — INFODOT ONLY

Remove independent login experiences from all other Dot platforms.

A user accessing an unauthenticated platform must be redirected to the central InfoDot login experience.

Example:

1. User visits `mines.example-domain`.
2. User selects Sign In.
3. The application redirects to InfoDot.
4. InfoDot authenticates the user.
5. InfoDot securely returns an authentication response to the registered Dot.Mines client.
6. Dot.Mines validates the response.
7. Dot.Mines establishes its own secure session.
8. The user continues using Dot.Mines.

Repeat this behaviour across every Dot platform.

### Important distinction

A platform may retain a branded Sign In button, but that button must initiate central authentication.

It must not render or process an independent password-login form.

The user's credentials must only be submitted to InfoDot over HTTPS.

Other Dot applications must never request, collect, store or transmit the user's InfoDot password.

Remove obsolete local authentication interfaces, password forms, registration routes and independent credential-handling logic after the central flow has been verified.

Do not accidentally remove legitimate platform-specific authorisation, user management, administrative roles or account settings.

---

## 7. SECURE REDIRECT ARCHITECTURE

This is one of the most important parts of the implementation.

The authentication redirect process must be resistant to:

- Open redirect attacks.
- Redirect manipulation.
- Phishing.
- Credential interception.
- Token theft.
- Authentication-code injection.
- Login CSRF.
- Session fixation.
- Replay attacks.
- Cross-platform token substitution.

### Mandatory protections

#### A. Registered redirect URIs

Every Dot platform must have explicitly registered redirect URIs.

- Require exact URI matching.
- Reject wildcard redirect domains.
- Reject unregistered callback URLs.
- Reject HTTP callbacks outside approved local development environments.
- Reject redirect URIs supplied by unauthorised clients.
- Never redirect based solely on a user-provided URL.

#### B. OAuth state validation

Generate cryptographically secure, unpredictable state values for every authentication request.

- Bind state to the initiating browser session.
- Validate state on callback.
- Reject missing or mismatched state.
- Make state single-use.
- Apply short expiry periods.
- Prevent replay.

#### C. PKCE

Require PKCE using S256 for every applicable authorization-code flow.

- Generate a secure code verifier.
- Generate the corresponding challenge.
- Bind the verifier to the initiating client session.
- Validate the verifier during token exchange.
- Reject missing or invalid PKCE verification.

#### D. OIDC nonce

Use a cryptographically secure nonce for authentication requests.

Validate the nonce against the returned ID token.

Reject missing, mismatched, expired or reused authentication responses.

#### E. Destination validation

Preserve the user's original destination without allowing arbitrary external redirects.

Use registered platform identifiers and validated relative paths or server-side stored destination state.

Reject:

- External redirect destinations.
- Protocol-relative URLs.
- Malformed URLs.
- Unregistered subdomains.
- Encoded redirect manipulation.
- Unexpected schemes.
- Callback URL substitution.

#### F. Authorization-code protection

Authorization codes must:

- Be short-lived.
- Be single-use.
- Be bound to the registered client.
- Be bound to the PKCE challenge.
- Be exchanged through the appropriate secure back-channel.
- Never be accepted from untrusted origins.

Never put access tokens, refresh tokens, client secrets or passwords in redirect URLs.

---

## 8. TOKEN SECURITY — PREVENT THEFT, SPOOFING AND REPLAY

Tokens must be treated as highly sensitive credentials.

Design token handling to minimise exposure.

### Token requirements

- Use cryptographically secure signing algorithms.
- Prefer asymmetric signing such as RS256 or ES256 where supported by the selected implementation.
- Reject unsigned tokens.
- Explicitly allow only approved signing algorithms.
- Validate issuer (`iss`).
- Validate audience (`aud`).
- Validate expiry (`exp`).
- Validate issued-at (`iat`) where appropriate.
- Validate nonce.
- Validate authorised client identity.
- Validate token scopes and permissions.
- Validate signature against trusted published signing keys.
- Reject malformed, expired, revoked or incorrectly signed tokens.

Do not trust token claims without cryptographic verification.

### Token lifetime

Use short-lived access tokens.

Implement appropriately secured refresh-token rotation where refresh tokens are required.

Refresh tokens must:

- Be protected against browser exposure.
- Be rotated after successful use.
- Be revoked when compromise is detected.
- Detect token reuse where supported.
- Be associated with the correct user, client and session.

### Token storage

Never store sensitive access or refresh tokens in:

- LocalStorage.
- SessionStorage.
- Public JavaScript variables.
- Browser-readable cookies.
- URLs.
- Query strings.
- Frontend logs.
- Analytics events.
- Error messages.

For browser-based Laravel applications, prefer a server-side token exchange and secure, HttpOnly application session cookie.

Where a browser-based architecture requires a token broker, use a Backend-for-Frontend (BFF) approach so browser JavaScript does not handle OAuth tokens directly.

### Signing keys

- Store private signing keys securely.
- Never expose private keys to frontend applications.
- Implement key rotation.
- Publish only appropriate public verification keys through JWKS.
- Support overlapping keys during rotation.
- Document emergency key revocation.
- Protect signing operations against unauthorised access.

A public JWKS endpoint is expected in OIDC. Its public verification keys must not expose private signing material.

---

## 9. SINGLE SIGN-ON ACROSS THE ECOSYSTEM

Implement a unified login experience.

Once authenticated through InfoDot, a user accessing another Dot platform should not have to enter their credentials again while their central session remains valid.

### Expected experience

Example:

1. User signs into InfoDot.
2. User opens Dot.Mines.
3. Dot.Mines initiates OIDC authentication.
4. InfoDot recognises the existing central session.
5. InfoDot completes the authentication flow without requesting credentials again.
6. Dot.Mines establishes its own secure session.
7. User opens Dot.Analytics.
8. The same process occurs without requiring another password entry.

Repeat across all authorised Dot platforms.

### Important security requirement

Do not share session cookies indiscriminately across all Dot domains.

Each application must maintain its own secure local session.

The central InfoDot session must be the source of authentication truth.

Use standard OIDC flows to establish trust between applications.

Avoid using a broad cookie domain such as `.infodot.co.za` as a shortcut for cross-platform SSO.

Different domains must not be trusted simply because they belong to the same ecosystem.

---

## 10. CENTRAL LOGOUT AND SESSION REVOCATION

Create a consistent logout experience across the Dot ecosystem.

When a user logs out:

1. Terminate the current platform session.
2. Initiate central InfoDot logout where appropriate.
3. Invalidate the central identity-provider session.
4. Revoke or invalidate relevant refresh tokens.
5. Notify other registered applications through supported logout mechanisms.
6. Terminate or expire their local sessions according to the implemented logout strategy.

Implement OIDC-compatible logout mechanisms where appropriate, including front-channel or back-channel logout.

Do not rely exclusively on browser redirects to invalidate other platform sessions.

Define how the system handles:

- User-initiated logout.
- Password changes.
- Password resets.
- Account suspension.
- Account deletion.
- Administrator-initiated revocation.
- Suspected account compromise.
- Device/session revocation.

A user must have a central session-management interface in InfoDot showing active sessions and devices where practical.

Provide a mechanism to terminate individual sessions and all sessions.

---

## 11. CENTRAL IDENTITY AND PLATFORM AUTHORISATION

Central authentication does not mean every user has automatic access to every platform.

InfoDot establishes identity.

Individual Dot platforms remain responsible for determining what an authenticated user is allowed to do.

Implement:

- Central user identity.
- Stable, immutable user identifier.
- Platform-specific access permissions.
- Tenant-aware authorisation.
- Role-based access control.
- Platform-specific entitlements.
- Subscription and licensing checks.
- Administrative privilege boundaries.

### Critical rules

- Never use email address as the primary cross-platform identity key.
- Use a stable immutable subject identifier (`sub`).
- Prevent users from changing their identity by modifying request parameters.
- Never trust role or permission claims without validation.
- Do not automatically grant administrator privileges.
- Ensure tenant boundaries are preserved.
- Ensure a user cannot gain access to another company's records through SSO.
- Enforce permissions server-side on every platform.

A successfully authenticated user is not automatically authorised to access every platform.

---

## 12. EXISTING USER AND DATABASE MIGRATION

This is a major part of the project.

Every Dot platform may currently have its own user table, password hashes, roles, permissions, subscriptions, ownership relationships and historical records.

Do not destroy or overwrite existing user data.

Before implementation:

1. Inventory user tables across every platform.
2. Identify duplicate accounts.
3. Identify users with different email addresses across platforms.
4. Identify existing password hashes and supported hashing algorithms.
5. Map existing user identifiers.
6. Identify platform-specific roles and permissions.
7. Identify business and tenant ownership.
8. Identify subscriptions and licensing relationships.
9. Identify historical records and audit trails.
10. Develop a safe migration and reconciliation strategy.

### Migration requirements

Create a central identity mapping between InfoDot users and existing platform accounts.

Use an immutable central identity identifier to map accounts.

Preserve:

- User profiles.
- Business associations.
- Platform roles.
- Permissions.
- Ownership relationships.
- Subscription records.
- Billing associations.
- Historical content.
- Audit records.
- Notifications.
- User-generated content.

Do not automatically merge accounts simply because their email addresses match.

Use verified identity matching and a safe account-linking process.

If duplicate or conflicting accounts exist, provide a controlled resolution workflow.

Existing users should not lose access to their historical content.

### Password migration

Never transfer plaintext passwords between platforms.

Where compatible password hashes can be securely migrated, evaluate a safe migration strategy.

Where hash compatibility or security cannot be established, require users to complete a secure InfoDot account activation or password-reset process.

Do not retain independent platform passwords after migration is completed and verified.

---

## 13. CENTRAL USER ACCOUNT MANAGEMENT

InfoDot must become the central place where users manage their Dot identity.

Implement:

- Account registration.
- Login.
- Logout.
- Email verification.
- Password changes.
- Password recovery.
- Multi-factor authentication.
- Active session management.
- Device management where practical.
- Connected platform overview.
- Account security notifications.
- Account suspension and recovery.
- Privacy and consent management.
- Account deletion workflows.

Users should be able to see which Dot platforms they have access to.

However, platform-specific settings and permissions must remain managed by the relevant platforms where appropriate.

---

## 14. CENTRAL IDENTITY SECURITY MONITORING

Implement monitoring for suspicious identity and authentication activity.

Detect and log:

- Repeated failed logins.
- Unusual authentication locations or patterns where appropriate.
- Repeated token failures.
- Invalid signatures.
- Invalid redirect requests.
- State mismatches.
- Nonce mismatches.
- PKCE failures.
- Token replay attempts.
- Refresh-token reuse.
- Suspicious session creation.
- Privilege escalation attempts.
- Unusual account-linking activity.
- Administrative identity changes.

Implement appropriate:

- Login rate limits.
- Token endpoint rate limits.
- Account recovery protection.
- Bot protection.
- Audit logging.
- Security alerts.
- Session revocation.
- Incident-response procedures.

Never log passwords, raw tokens, private keys, or sensitive authentication codes.

---

## 15. CENTRAL IDENTITY API SECURITY

All identity-related APIs must be protected.

Requirements:

- Strict authentication and authorisation.
- Registered client validation.
- Token audience validation.
- Scope enforcement.
- Rate limiting.
- Input validation.
- Safe error handling.
- Replay protection.
- Request integrity where applicable.
- Appropriate CORS configuration.
- Secure key management.
- Audit logging.

Do not assume that hiding API endpoints provides security.

Do not expose internal identity-management operations to ordinary platform clients.

Do not allow one Dot platform to request another platform's privileges.

Implement least-privilege client scopes.

---

## 16. PLATFORM INTEGRATION REQUIREMENTS

Every Dot platform must have a consistent integration implementation.

Create a reusable authentication integration package or SDK compatible with the existing application architecture.

Where Laravel is used, evaluate a shared Laravel authentication integration package that provides:

- OIDC client configuration.
- Authentication redirects.
- Callback handling.
- Token validation.
- Session creation.
- Session termination.
- Logout handling.
- User identity mapping.
- Error handling.
- Security logging.

Avoid duplicating security-critical authentication code independently across every platform.

Each platform should have a minimal, consistent integration layer.

The central identity provider must remain the only authority responsible for credentials.

---

## 17. REMOVE LEGACY AUTHENTICATION

After central authentication has been fully integrated and verified:

- Remove independent registration forms.
- Remove independent login forms.
- Remove independent password-management interfaces.
- Remove local password-reset functionality.
- Remove obsolete authentication routes.
- Remove unused password-related controllers.
- Remove obsolete authentication middleware.
- Remove insecure legacy token mechanisms.
- Remove duplicate credential storage where safe and appropriate.

Do not remove platform-specific user management, permissions, profile settings or administrative functions.

Do not delete old authentication data until migration is verified and rollback requirements are satisfied.

The final result must not leave hidden alternative login paths or legacy authentication endpoints that bypass InfoDot.

---

## 18. TESTING AND ACCEPTANCE SCENARIOS

Create automated integration and security tests covering the entire ecosystem.

At minimum, test:

### Registration

- Register from InfoDot.
- Start registration from Dot.Mines.
- Start registration from Dot.Analytics.
- Start registration from every other platform.
- Confirm registration always occurs through InfoDot.
- Confirm the user returns to the correct originating platform.
- Confirm arbitrary redirect destinations are rejected.

### Login

- Sign in through InfoDot.
- Access every registered Dot platform.
- Confirm SSO works.
- Confirm users are not repeatedly asked for credentials.
- Confirm invalid authentication responses are rejected.
- Confirm an unregistered application cannot authenticate users.

### Token security

- Expired token.
- Incorrect token signature.
- Incorrect issuer.
- Incorrect audience.
- Invalid nonce.
- Invalid state.
- Invalid PKCE verifier.
- Reused authorization code.
- Replayed refresh token.
- Revoked token.
- Token issued for the wrong platform.
- Attempted privilege escalation.

Every invalid scenario must be rejected.

### Redirect security

- External redirect URL.
- Unregistered subdomain.
- Protocol-relative URL.
- Encoded malicious redirect.
- Callback substitution.
- Redirect parameter manipulation.

Every unauthorised destination must be rejected.

### Cross-platform security

- User from one tenant attempts to access another tenant's data.
- User attempts to access a platform without entitlement.
- User attempts to use another platform's token.
- User attempts to access administrative functions.
- Suspended account attempts authentication.
- Revoked session attempts to access a platform.

Every unauthorised request must fail securely.

### Logout

- Logout from InfoDot.
- Logout from Dot.Mines.
- Logout from another Dot platform.
- Password reset.
- Account suspension.
- Global session revocation.

Verify the expected session termination behaviour.

---

## 19. DEPLOYMENT AND ROLLOUT

Do not attempt to migrate every platform simultaneously without a controlled rollout.

Implement a phased migration.

### Phase 1 — Discovery

- Inventory all applications.
- Identify authentication implementations.
- Map domains and redirect URLs.
- Map existing users and roles.
- Identify dependencies.

### Phase 2 — Central identity foundation

- Implement the InfoDot identity provider.
- Configure OIDC.
- Configure registered clients.
- Implement signing keys.
- Implement token and session management.
- Complete security testing.

### Phase 3 — Pilot integration

Integrate one suitable Dot platform first.

Verify registration, login, SSO, logout, authorisation, and token security.

### Phase 4 — Platform migration

Migrate remaining platforms in controlled groups.

Each platform must pass its own integration tests before being switched over.

### Phase 5 — Legacy authentication removal

Only after successful migration:

- Disable old login endpoints.
- Disable old registration endpoints.
- Remove obsolete authentication code.
- Verify there are no authentication bypasses.

### Phase 6 — Ecosystem-wide verification

Test cross-platform SSO, account migration, tenant isolation, central logout and security controls.

### Phase 7 — Production rollout

Use staged deployment, monitoring and rollback procedures.

Never leave production users in an inconsistent state where some platforms require the old login and others require the new identity provider without a controlled transition plan.

---

## 20. DOCUMENTATION AND DELIVERABLES

Produce the following:

1. Complete Dot ecosystem platform inventory.
2. Existing authentication architecture assessment.
3. Central identity-provider architecture.
4. OIDC/OAuth configuration.
5. Registered client application inventory.
6. Redirect URI registry.
7. Token and signing-key strategy.
8. Central session and logout architecture.
9. User migration and identity-mapping strategy.
10. Reusable platform integration implementation.
11. Platform-by-platform migration status.
12. Security threat model.
13. Automated test results.
14. Penetration-test findings.
15. Deployment and rollback procedures.
16. Ongoing identity security monitoring strategy.
17. Operational documentation for administrators.

Provide a final platform status matrix:

| Platform | Domain preserved | Central login | Central registration | SSO | Logout | Security tests |
|---|---|---|---|---|---|---|
| Dot.Mines | Required | Required | Required | Required | Required | Required |
| Dot.Analytics | Required | Required | Required | Required | Required | Required |
| Dot.Brain | Required | Required | Required | Required | Required | Required |
| All remaining platforms | Required | Required | Required | Required | Required | Required |

Do not mark a platform as complete until its actual authentication flow has been tested.

---

# FINAL ARCHITECTURAL PRINCIPLE

InfoDot is the identity authority for the entire Dot ecosystem.

Every other Dot platform trusts InfoDot to authenticate users but independently enforces its own permissions, subscriptions, tenant boundaries and business rules.

There must be:

- One central identity.
- One registration authority.
- One credential-management authority.
- One central authentication experience.
- One consistent SSO architecture.
- Secure platform-specific sessions.
- Independent platform authorisation.
- No alternative or hidden login systems.

**Preserve every platform's domain, identity of purpose, branding, functionality and data. Centralise only identity and authentication.**

Security must be designed around the assumption that attackers will actively attempt to intercept redirects, steal tokens, spoof credentials, manipulate callbacks, exploit session behaviour and move laterally between platforms.

Do not claim the architecture is secure merely because OIDC has been implemented. Validate the entire trust chain, every registered client, every redirect, every token exchange, every session and every platform's authorisation boundary.

The final result must be a unified, enterprise-grade Dot ecosystem where a user has one trusted identity, managed through InfoDot, and can securely access every platform they are authorised to use.
