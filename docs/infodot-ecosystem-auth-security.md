# InfoDot Ecosystem Auth Security

## Purpose

The ecosystem SSO mechanism — where InfoDot issues tokens and satellite Dot platforms accept them — is the highest-value attack surface in the system. A token leak or a badly implemented handoff endpoint gives an attacker authenticated access across every connected platform. This guide covers the design rules, implementation requirements, and security properties that must hold across the entire ecosystem.

---

## Token Types In The Ecosystem

InfoDot issues two kinds of tokens, and they have completely different security profiles:

| Token type | Lifetime | Use | Storage |
|---|---|---|---|
| Long-lived Sanctum API token | 24 hours (configurable) | API access from satellite apps | `personal_access_tokens` table, hashed |
| Short-lived handoff token | 5 minutes | One-time cross-platform login redirect | `personal_access_tokens` table, hashed |

Never use a long-lived API token as a handoff token. Never accept a handoff token more than once.

---

## Long-Lived Sanctum Token Rules

These tokens are used by satellite platforms to authenticate API calls against the shared InfoDot database.

**Issuance rules:**

- Tokens must be issued only through authenticated requests by the token owner.
- Tokens must carry explicit `abilities` scopes. Do not issue `['*']` tokens for ecosystem use. Use narrow scopes like `['platform:files']`, `['platform:agents']`.
- Always set `expires_at` when creating tokens. Never issue tokens with `null` expiry for platform access.
- Store only the hash. The plaintext token is shown exactly once at creation. Log a warning if any code path attempts to read back the plaintext value.

**Verification rules on satellite apps:**

- Call `PersonalAccessToken::findToken($token)` to retrieve the token record.
- Check `$token->expires_at` is in the future before accepting.
- Check the token has the required ability for the operation: `$token->can('platform:files')`.
- Never cache token verification results. Always verify against the live database.

**Revocation rules:**

- Revoke tokens immediately on password change or reset: `$user->tokens()->delete()`.
- Provide a user-facing "active sessions" screen that lists and allows revocation of individual tokens.
- Auto-expire tokens server-side on a scheduled command — do not rely on the client to stop sending an expired token.

---

## Short-Lived Handoff Token Rules

These tokens exist solely to log a user into a satellite platform via a redirect URL.

**Issuance:**

```php
// Issued in InfoDot, for example from the Dot Switcher component
$token = $user->createToken(
    name: 'handoff:' . $platform,
    abilities: ['handoff'],
    expiresAt: now()->addMinutes(5),
)->plainTextToken;

return redirect("https://{$platform}.infodot.app/auth/ecosystem?token={$token}");
```

**Acceptance on the satellite app:**

```php
public function handle(Request $request): RedirectResponse
{
    $accessToken = PersonalAccessToken::findToken($request->query('token'));

    abort_if(
        !$accessToken
        || !$accessToken->can('handoff')
        || $accessToken->expires_at->isPast(),
        403,
        'Invalid or expired handoff token.'
    );

    $user = $accessToken->tokenable;

    // Delete BEFORE logging in — prevents race condition double-use
    $accessToken->delete();

    Auth::login($user, remember: false);

    return redirect()->route('dashboard');
}
```

**Critical rules:**

- Delete the token inside the same operation as the login, before the redirect.
- Never pass handoff tokens in URL fragments — use query parameters over HTTPS only.
- Never log the plaintext token value.
- Never accept a handoff token that has the wrong `abilities` value.
- The endpoint must not be cached by proxies or CDNs.
- Rate-limit this endpoint per IP.

---

## CORS Policy

All satellite Dot platforms make cross-origin requests to InfoDot's API. The CORS configuration must be explicit.

`config/cors.php`:

```php
return [
    'paths'               => ['api/*', 'auth/*'],
    'allowed_methods'     => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'],
    'allowed_origins'     => [
        'https://files.infodot.app',
        'https://agents.infodot.app',
        'https://docs.infodot.app',
        // add each platform explicitly
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers'     => ['Content-Type', 'Authorization', 'X-Requested-With'],
    'exposed_headers'     => [],
    'max_age'             => 0,
    'supports_credentials' => true,
];
```

Never set `'allowed_origins' => ['*']` on authenticated API routes.

---

## Sanctum Configuration Hardening

`config/sanctum.php`:

```php
// Stateful domains — InfoDot's own domain only
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'infodot.app')),

// Token expiry — override per token at issuance, this is the fallback
'expiration' => 60 * 24, // 24 hours in minutes

// Guard for token verification
'guard' => ['web'],
```

`.env` additions for production:

```env
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
SANCTUM_STATEFUL_DOMAINS=infodot.app
```

---

## Token Storage Security On Satellite Apps

Satellite platforms that receive a long-lived API token to communicate with InfoDot must not store that token in places it can leak:

- Do not store tokens in cookies accessible to JavaScript.
- Do not store tokens in `localStorage`.
- Store tokens in a server-side session, encrypted environment variable, or a secrets manager.
- Rotate tokens on a schedule by calling the InfoDot token issuance endpoint with a long-lived admin credential.

---

## Attack Scenarios And Mitigations

| Scenario | Mitigation |
|---|---|
| Attacker intercepts handoff token in URL | HTTPS only, short expiry, one-time use |
| Attacker replays a handoff token | Token deleted on first use |
| Attacker obtains long-lived API token | Narrow ability scopes limit blast radius; expiry limits window |
| Satellite app is compromised | Token abilities only permit that platform's operations |
| Brute-force token guessing | Sanctum tokens are 80-character random strings; rate limiting on verify |
| Mass token issuance (DoS) | Rate limiting on `/api/ecosystem/token`; authenticated issuance only |
| Session fixation on handoff | Login is always a fresh session — `Auth::login()` rotates session ID |

---

## Ecosystem Security Checklist

Before enabling any new Dot platform:

- [ ] Platform's `/auth/ecosystem` endpoint deletes the handoff token before logging in
- [ ] Platform verifies token has `handoff` ability
- [ ] Platform verifies token has not expired
- [ ] Platform's CORS origins are registered in InfoDot `config/cors.php`
- [ ] Platform stores any long-lived tokens outside browser-accessible storage
- [ ] Platform's API calls use the `Authorization: Bearer` header, not query string tokens
- [ ] Rate limiting is active on the platform's auth endpoint
- [ ] InfoDot's token issuance endpoint for this platform has an appropriate ability scope
