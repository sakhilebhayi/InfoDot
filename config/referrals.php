<?php

/**
 * R-59 Phase 1C: the referral programme's rules. Every window and limit is here, none is hard-coded where it is used.
 * Reward amounts are NOT here: they live in the referral_tiers table so an administrator can change them.
 */
return [
    // A referral is credited only when the referred person arrived (cookie touch) at most this long before registering.
    'window_days' => 30,

    // A referred business must show a second sign of use inside this many days of registering to count as activated.
    'activation_days' => 30,

    // A reward is held this long before it can be issued, so a refund, cancellation or fraud can still reverse it.
    'holding_days' => 30,

    // At most this many people may register through one code in one day; the rest are recorded as refused.
    'daily_cap_per_code' => 25,

    // Addresses at these providers say nothing about a shared employer, so the same-domain block ignores them.
    'public_email_domains' => ['gmail.com', 'googlemail.com', 'outlook.com', 'hotmail.com', 'live.com', 'yahoo.com', 'icloud.com', 'me.com', 'proton.me', 'protonmail.com', 'mweb.co.za', 'webmail.co.za', 'telkomsa.net', 'vodamail.co.za'],

    // Rewards are recorded and held here. They are only sent to the points ledger when this is switched on, which waits for the
    // Dot.Dopemine contract (event types, milestone rules, production secret). Until then rewards stay "due" and nothing is sent.
    'send_points' => (bool) env('REFERRALS_SEND_POINTS', false),

    'code_alphabet' => 'ABCDEFGHJKMNPQRSTUVWXYZ23456789',
    'code_length' => 8,
];
