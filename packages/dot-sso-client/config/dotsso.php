<?php

/*
 * Dot ecosystem SSO, relying-party side (R-20 §16). One canonical implementation, vendored into
 * each platform repo at packages/dot-sso-client and installed as a composer path repository, so no
 * platform hand-rolls authentication code and the shared host needs no external package registry.
 *
 * Nothing changes while DOTSSO_ENABLED is false: the platform keeps its local Fortify login. With
 * it true, local login/registration routes are intercepted and every sign-in goes through InfoDot.
 * That flag is the per-platform rollout switch AND the instant rollback (R-20 §19).
 */
return [
    'enabled' => env('DOTSSO_ENABLED', false),

    // The identity provider. Endpoints are derived from the issuer per OIDC discovery; they are
    // resolved once and cached, never hardcoded per platform.
    'issuer' => rtrim(env('DOTSSO_ISSUER', 'https://infodot.co.za'), '/'),

    'client_id' => env('DOTSSO_CLIENT_ID'),
    'client_secret' => env('DOTSSO_CLIENT_SECRET'),

    'scopes' => 'openid profile email',

    // Local user provisioning: create an account on first SSO sign-in when none is linked.
    'provision_users' => env('DOTSSO_PROVISION_USERS', true),

    // Link an existing unlinked local account by email ONLY when both sides are verified:
    // the id_token says email_verified AND the local account has a verified email. Anything less
    // (unverified local account with the same email) is refused and logged, never silently adopted.
    'link_verified_email' => env('DOTSSO_LINK_VERIFIED_EMAIL', true),

    // Seconds to cache the provider's JWKS and discovery document.
    'jwks_cache_seconds' => 3600,

    // Clock skew tolerated when validating token timestamps.
    'leeway_seconds' => 60,

    // Where a signed-in user lands when no safe intended path is known.
    'home' => '/dashboard',
];
