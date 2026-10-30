<?php

use Lcobucci\JWT\Signer\Rsa\Sha256;
use OpenIDConnect\Repositories\IdentityRepository;

/*
 * R-20: OpenID Connect settings for the central identity provider (jeremy379/laravel-openid-connect
 * on top of Laravel Passport). The package's discovery and userinfo routes are replaced by our own
 * (app/Http/Controllers/Oidc) so the metadata states exactly what this server enforces — S256-only
 * PKCE, code flow only, the end-session endpoint and back-channel logout — and so userinfo's `sub`
 * is the user's stable ecosystem uuid, never the numeric id. The package's JWKS route is kept.
 */
return [
    'passport' => [
        // The only scopes the Dot identity provider grants. Platform entitlements are NOT scopes:
        // each platform decides authorisation locally (R-20 §11).
        'tokens_can' => [
            'openid' => 'Confirm your identity',
            'profile' => 'Your name',
            'email' => 'Your email address',
        ],
    ],

    'custom_claim_sets' => [],

    'repositories' => [
        'identity' => IdentityRepository::class,
    ],

    'routes' => [
        'discovery' => false,   // ours: App\Http\Controllers\Oidc\DiscoveryController
        'jwks' => true,         // package route at /oauth/jwks (reads the kid below)
        'jwks_url' => '/oauth/jwks',
        'userinfo' => false,    // ours: sub must be the uuid, not getAuthIdentifier()
    ],

    'discovery' => [
        'hide_scopes' => false,
    ],

    'signer' => Sha256::class,

    // The kid ties id_tokens, logout tokens and the JWKS entry to one named key, so a future key
    // rotation can serve two JWKS entries side by side.
    'token_headers' => ['kid' => env('OIDC_KID', 'infodot-oidc-1')],

    'use_microseconds' => true,

    'issuedBy' => 'laravel',

    'forceHttps' => env('OIDC_FORCE_HTTPS', true),
];
