<?php

/*
|--------------------------------------------------------------------------
| Participation (R-9 section 6): opt-in expertise signals sent to Dot.Memory
|--------------------------------------------------------------------------
| InfoDot tells Dot.Memory which topics a person helped with (topic and count, never content), but only for a person
| who switched it on, and only while it is on. Everything here is server to server and signed with the same
| HMAC-SHA256 scheme as the Dot Points calls. Memory holds the matching secret as PARTICIPATION_SECRET_INFODOT.
| Off by default and fail-closed: unconfigured means nothing is sent, nothing is claimed, and the switch on the
| Dot Points page says so instead of pretending.
*/

return [

    // The master switch. Without it the page offers no way to turn sharing on and no event is ever queued.
    'enabled' => (bool) env('PARTICIPATION_ENABLED', false),

    'url' => env('MEMORY_URL', 'https://memory.infodot.co.za'),

    // Same value as PARTICIPATION_SECRET_INFODOT on Dot.Memory. Never committed, never logged, never shown.
    'secret' => env('PARTICIPATION_SECRET'),

    'timeout' => (int) env('PARTICIPATION_TIMEOUT', 5),

    // Memory deletes events older than its retention (365 days by default), so older contributions are not sent.
    'backfill_max_age_days' => (int) env('PARTICIPATION_BACKFILL_DAYS', 365),
];
