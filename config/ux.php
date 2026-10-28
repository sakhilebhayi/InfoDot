<?php

/*
|--------------------------------------------------------------------------
| User interaction signals (R-12, first increment)
|--------------------------------------------------------------------------
|
| Daily COUNTS of friction on InfoDot's own pages, so the team can see which page fails people. Off by default: the owner
| switches it on with UX_SIGNALS=true once the consent basis is confirmed (docs/programme/R12_UX_SIGNALS.md).
|
| What is stored, and all of it: a day, a ROUTE TEMPLATE ("question/{qid}", never the address that was opened), an event type
| from the Dot.Brain contract (services/ux-interactions, ADR-0025), a status, an error category, a coarse speed bucket and a
| count. No person, no session, no address, no query text, no form content, no cookie. Basis: aggregate_only.
*/

return [
    'enabled' => (bool) env('UX_SIGNALS', false),

    // A request slower than this is counted as slow (milliseconds).
    'slow_ms' => (int) env('UX_SLOW_MS', 1500),

    // Counts older than this many days are removed by `ux:prune`.
    'retention_days' => (int) env('UX_RETENTION_DAYS', 180),

    // Findings (advisory only). A rule needs at least `min_events` observations in the window before it says anything.
    'rules' => [
        'min_events' => 20,
        'validation_rate' => 0.20,   // share of submissions on a form that fail validation
        'empty_search_rate' => 0.30, // share of searches that find nothing
        'slow_rate' => 0.20,         // share of page views slower than slow_ms
        'not_found_count' => 10,     // 404s on a route in the window
        'server_error_count' => 3,   // 5xx on a route in the window
    ],
];
