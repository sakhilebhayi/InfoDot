<?php

/**
 * R-59 Phase 1A: where a sign-up came from. First-party, minimal, and only when the visitor arrives through a tagged link or from a search
 * or social site. No IP address, no device fingerprint, nothing shared with another site.
 */
return [
    // The one cookie. It is set only on an arriving visit that carries a referral code, a campaign tag or a search/social referrer, and
    // the first such visit wins. It is encrypted by the framework, so a changed value is read as no value.
    'cookie' => 'dot_src',
    'days' => 30,

    // R-71: dot_src is not strictly necessary, so it is only set after the visitor accepts it in the cookie banner. Until they choose, a
    // tagged arrival waits in the visit's own session; Decline discards it. The choice itself is kept in this strictly necessary cookie.
    'consent_cookie' => 'dot_consent',
    'consent_days' => 365,
    'pending_key' => 'acquisition.pending',

    // A referral code: letters, digits and hyphens. (Codes are issued in Phase 1C; until then an unknown code is recorded as text only.)
    'ref_pattern' => '/^[A-Za-z0-9-]{4,32}$/',

    // Campaign tag fields we keep, and the longest value kept for each.
    'utm_fields' => ['source', 'medium', 'campaign', 'term', 'content'],
    'utm_max' => 80,

    // utm_medium values that name a channel from the growth strategy (Phase 2 adds the partner and institution records behind them).
    'medium_channels' => ['partner' => 'partner', 'institution' => 'institutional', 'institutional' => 'institutional', 'referral' => 'referral'],

    // Referring hosts read as organic discovery. The host only is kept, never the address.
    'organic_hosts' => ['google.', 'bing.com', 'duckduckgo.com', 'yahoo.', 'ecosia.org', 'brave.com', 'startpage.com', 'baidu.com', 'yandex.',
        'facebook.com', 'l.facebook.com', 'instagram.com', 'linkedin.com', 'lnkd.in', 'x.com', 'twitter.com', 't.co', 'tiktok.com', 'youtube.com', 'reddit.com', 'whatsapp.com'],

    'channels' => ['referral', 'partner', 'institutional', 'organic', 'direct', 'product_led', 'campaign'],
];
