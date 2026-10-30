<?php

/*
 * R-20: ecosystem single sign-on, provider side. InfoDot is the OpenID Provider for every Dot
 * platform; the platform registry (dot_platforms) is the list of relying parties allowed to use it.
 */
return [
    // Seconds a back-channel logout POST to a platform may take before it is logged and skipped.
    'backchannel_timeout' => (int) env('SSO_BACKCHANNEL_TIMEOUT', 5),

    // Lifetime of a logout token (OIDC Back-Channel Logout 1.0 keeps these very short).
    'logout_token_ttl' => 120,

    // Minimum entropy the authorize endpoint demands from relying parties.
    'min_state_length' => 16,
    'min_nonce_length' => 8,
];
