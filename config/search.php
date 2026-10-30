<?php

/*
|--------------------------------------------------------------------------
| Search behaviour (R-16 phase 6)
|--------------------------------------------------------------------------
| One place for the knobs every search box shares. Client-side debounce keeps typing from becoming a request per
| keystroke; the server throttles, caches and limits regardless, so these are tuning, not protection.
*/

return [

    // How long a pause in typing is before a search fires (header search, pickers, @mentions).
    'debounce_ms' => (int) env('SEARCH_DEBOUNCE_MS', 400),

    // Duplicate detection reads whole drafts, so it waits a little longer.
    'similar_debounce_ms' => (int) env('SEARCH_SIMILAR_DEBOUNCE_MS', 700),

    // A server-side search slower than this is logged (request id attached), so slow queries surface in the daily log.
    'log_slow_ms' => (int) env('SEARCH_LOG_SLOW_MS', 800),

    // When a results page finds fewer than this many hits, close matches by meaning are offered under the results.
    'related_fallback_below' => 3,
];
