# InfoDot Security Hardening Guide

## Purpose

InfoDot is the authentication and identity hub for a multi-platform ecosystem. A security failure here affects every Dot platform connected to it. This guide maps the OWASP Top 10 to the InfoDot stack and provides concrete hardening steps for each area.

---

## OWASP Top 10 — InfoDot Mapping

### A01 — Broken Access Control

InfoDot uses Laravel Policies for authorization. Risks specific to this codebase:

- team-scoped resources accessed by users outside the team
- file objects accessed without ownership checks
- ecosystem handoff tokens accepted after expiry or reuse

**Hardening actions:**

- Register a Policy for every model that owns a resource: `Solution`, `Question`, `Comment`, `Team`, and file objects.
- Gate all controller actions and Livewire component methods that mutate data with `$this->authorize()` or `Gate::authorize()`.
- Never rely solely on route parameters to determine access — always verify ownership in the policy.
- Ensure handoff tokens are deleted immediately on first use and have a hard 5-minute expiry checked at verify time.
- Write a policy test for every role boundary: guest, member, team admin, platform admin.

### A02 — Cryptographic Failures

Sensitive data in InfoDot includes user credentials, Sanctum tokens, Stripe keys, and cross-platform handoff tokens.

**Hardening actions:**

- Confirm `APP_KEY` is a 32-character random value and rotated on a schedule in production.
- Never store raw tokens. Sanctum hashes tokens by default — do not override this.
- Ensure `HTTPS` is enforced in production via middleware and `config/session.php` `secure` setting.
- Set `SESSION_SECURE_COOKIE=true` and `SESSION_SAME_SITE=lax` in production `.env`.
- Never log sensitive values. Add a custom log filter or scrubber if needed.
- Ensure `config/hashing.php` uses `bcrypt` with a cost of at least 12 or `argon2id`.

### A03 — Injection

Laravel's Eloquent ORM and query builder protect against SQL injection when used correctly. The risks come from raw query strings.

**Hardening actions:**

- Never interpolate user input directly into `DB::raw()`, `whereRaw()`, or `selectRaw()`.
- Always use named bindings when raw expressions are unavoidable: `whereRaw('created_at > ?', [$date])`.
- Sanitize HTML-rich user content (solution descriptions, Q&A bodies) using a whitelist-based purifier before storing.
- Validate all input at the `FormRequest` layer before it reaches the model.
- Review all search query paths — Scout handles this correctly, but any fallback raw LIKE queries must use bound parameters.

### A04 — Insecure Design

InfoDot's ecosystem design introduces unique risks: a compromised handoff token grants cross-platform access.

**Hardening actions:**

- Short-lived handoff tokens must be one-time use. Delete the token inside the same database transaction as the login.
- Rate-limit the `/api/ecosystem/token` endpoint aggressively — this endpoint should not be callable in bulk.
- The `personal_access_tokens` table is shared across platforms. Any satellite app must never be able to issue tokens — only InfoDot issues them.
- Add an `abilities` constraint to Sanctum tokens that limits what each token can do by platform.
- Treat unauthenticated requests to any Dot platform as an expired session, not just a missing token.

### A05 — Security Misconfiguration

**Hardening actions:**

- Set `APP_DEBUG=false` in all non-development environments.
- Remove or protect `/telescope` in production. Telescope should require auth and should be disabled entirely if not needed in production.
- Remove default credentials, example `.env` entries, and unused service keys.
- Review `config/cors.php` — allowed origins must be an explicit whitelist of Dot platform domains, not a wildcard.
- Disable directory listing on the web server.
- Set security headers on all responses (see HTTP headers section below).

### A06 — Vulnerable and Outdated Components

**Hardening actions:**

- Run `composer audit` and `npm audit` before every release.
- Pin Composer and npm package versions to known good ranges. Do not accept `*` constraints.
- Track the Laravel security release channel and apply patch releases within 48 hours.
- Establish a policy: no unreviewed major version upgrades to auth-related packages.

### A07 — Identification and Authentication Failures

**Hardening actions:**

- Enforce a minimum password length of 12 characters in `config/fortify.php` password rules.
- Lock accounts after a configurable number of failed login attempts. Use Laravel's built-in throttle middleware on the login route.
- Ensure Sanctum tokens have an `expires_at` set for all programmatic access. Never issue non-expiring tokens to frontend clients.
- Invalidate all active tokens on password reset.
- Provide a "sign out everywhere" action in user settings that calls `$user->tokens()->delete()`.
- Consider adding TOTP-based two-factor authentication via Jetstream's built-in 2FA features.

### A08 — Software and Data Integrity Failures

**Hardening actions:**

- Verify the integrity of any uploaded files. Do not trust MIME types from the client — re-detect them server-side.
- For production asset delivery, use the Vite build manifest to ensure clients load the correct versioned assets.
- Use `php artisan config:cache` and `php artisan route:cache` consistently so cached artifacts are reproducible.
- Sign any webhook payloads InfoDot sends to satellite apps.

### A09 — Security Logging and Monitoring Failures

**Hardening actions:**

- Log all authentication events: successful login, failed login, logout, token issuance, token revocation, password reset.
- Log all ecosystem token handoff attempts, both successful and failed.
- Log authorization failures at warning level.
- Route logs to a centralized store in production (Sentry, a log aggregator, or cloud logging).
- Alert on repeated authentication failures from the same IP within a short window.
- Never log request bodies that may contain passwords, tokens, or payment data.

### A10 — Server-Side Request Forgery

InfoDot does not appear to fetch arbitrary user-supplied URLs today, but as Dot platform integrations grow this risk increases.

**Hardening actions:**

- If InfoDot ever fetches a URL on behalf of a user or a connected platform, validate the URL against an allowlist of known Dot domains.
- Block requests to private IP ranges, loopback addresses, and cloud metadata endpoints.
- Do not pass raw user-supplied URLs to `Http::get()` or `file_get_contents()`.

---

## HTTP Security Headers

Add the following headers to all responses. The cleanest place is a dedicated `SecurityHeaders` middleware registered globally.

```php
// app/Http/Middleware/SecurityHeaders.php
public function handle(Request $request, Closure $next): Response
{
    $response = $next($request);

    $response->headers->set('X-Content-Type-Options', 'nosniff');
    $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
    $response->headers->set('X-XSS-Protection', '1; mode=block');
    $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    $response->headers->set(
        'Content-Security-Policy',
        "default-src 'self'; script-src 'self' 'nonce-{$nonce}'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; connect-src 'self' wss:;"
    );

    return $response;
}
```

Start with a report-only CSP in staging before enforcing it in production.

---

## Rate Limiting

Define rate limiters in `app/Providers/RouteServiceProvider.php` or the new `bootstrap/app.php` pattern for Laravel 12:

```php
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});

RateLimiter::for('ecosystem-token', function (Request $request) {
    return Limit::perMinute(10)->by($request->ip());
});

RateLimiter::for('login', function (Request $request) {
    return Limit::perMinute(5)->by($request->ip());
});
```

---

## File Upload Security

InfoDot handles user file uploads. Apply the following rules for every upload endpoint:

- Validate MIME type server-side using `finfo` or `League\MimeTypeDetection`, not the client-supplied `Content-Type`.
- Enforce maximum file size in both application validation and web server config.
- Store uploaded files outside the public web root or use S3 with pre-signed URLs.
- Scan filenames for path traversal: reject any name containing `..`, `/`, or `\`.
- Generate a new UUID-based filename on storage. Never use the original filename as the stored key.
- If serving files back, set `Content-Disposition: attachment` for non-image types.

---

## Security Release Checklist

Before each release, confirm:

- [ ] `composer audit` reports no known vulnerabilities
- [ ] `npm audit` reports no high or critical issues
- [ ] `APP_DEBUG=false` in production
- [ ] `APP_ENV=production` in production
- [ ] All tokens have explicit `expires_at` values
- [ ] Rate limiters are active on auth, API, and ecosystem endpoints
- [ ] Security headers are present on a sample of responses
- [ ] No sensitive values are written to logs
- [ ] CORS `allowed_origins` is an explicit whitelist
- [ ] Sentry or equivalent is receiving errors
