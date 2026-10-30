# dot/sso-client — STATUS: INCOMPLETE, DO NOT ENABLE

Relying-party integration for the Dot ecosystem's central identity provider (InfoDot, R-20).
This package is **not finished and not installed by any platform yet**. `DOTSSO_ENABLED`
must stay `false` (the default) until the missing pieces below exist and the pilot platform's
test suite covers them.

## What is here (done)

- `config/dotsso.php` — issuer, client credentials from env, rollout flag, linking policy.
- `src/DotSsoServiceProvider.php` — env-gated wiring (config merge, routes, middleware groups).
- `routes/sso.php` — the relying-party endpoints.
- `database/migrations/…add_sso_columns.php` — `users.sso_sub` (immutable subject link) and
  `sso_revocations` (back-channel logout honoured regardless of session driver).
- `src/Jwt/ProviderMetadata.php` — OIDC discovery + JWKS retrieval, cached, issuer-pinned,
  JWK→PEM conversion.
- `src/Jwt/TokenVerifier.php` — strict RS256 verification for id_tokens (iss/aud/exp/iat/nonce/azp)
  and logout tokens (events claim, freshness, jti replay cache).

## What is missing (the classes the routes and provider reference)

- `src/Http/Controllers/SsoController.php` — `redirect` (build the authorize URL with state,
  nonce and a PKCE S256 pair kept in the session), `callback` (single-use state check, code→token
  exchange at the discovered token endpoint, `TokenVerifier::verifyIdToken`, account resolution,
  session login + regenerate, safe local-path redirect), `logout` (local logout then the provider's
  end-session endpoint with `id_token_hint` and the registered post-logout URI).
- `src/Http/Controllers/BackchannelLogoutController.php` — accept `logout_token`, run
  `TokenVerifier::verifyLogoutToken`, record the subject in `sso_revocations`, answer 200.
- `src/Http/Middleware/InterceptLocalAuth.php` — while enabled, send the local login/register
  GET routes to `dotsso.redirect` / the provider's registration, and refuse credential POSTs to
  the local Fortify endpoints (R-20 §6).
- `src/Http/Middleware/EnforceSsoRevocation.php` — log out any session whose `sso_sub` has a
  revocation newer than the session's sign-in time.
- `src/UserProvisioner.php` — find the local user by `sso_sub`; else link an existing account by
  email only when both the claim and the local account are verified (configurable); else create
  one when provisioning is on (with a personal team on Jetstream platforms). Never merge by bare
  email match (R-20 §12).

## Rollout contract (unchanged)

Platform repos vendor this package (composer path repository), merge it disabled, and switch on
per host via `.env` after the owner registers the platform on InfoDot (`php artisan sso:platform
register …`) — which prints the one-time client credentials. Rollback = flip the flag off.
