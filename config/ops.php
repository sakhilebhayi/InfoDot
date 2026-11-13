<?php

/*
|--------------------------------------------------------------------------
| Operations: alerts and checks
|--------------------------------------------------------------------------
|
| The host has no error tracker and no outside monitor, so the application watches itself: `ops:check` counts the
| conditions that fail silently, and `ops:alert` (every ten minutes) emails one address when the daily log gains
| error lines or a check stops passing. The email holds counts and masked first lines only: no stack trace, no
| request data, nothing that names a person.
*/

return [
    // Where the alert goes. Empty = no alert is sent (one info line a day says so).
    'alert_address' => env('OPS_ALERT_ADDRESS'),

    // At most one alert in this many minutes; what happens in between is counted and carried into the next one.
    'alert_every_minutes' => 60,

    // Where ops:alert remembers how far it has read. A file, not the cache: a deploy clears the cache, and the
    // alert must not repeat a day's errors after every release.
    'alert_state_path' => storage_path('framework/ops-alert.json'),

    // ops:check reports a problem when the disk holding storage/ has less free space than this.
    'min_free_bytes' => (int) env('OPS_MIN_FREE_MB', 1024) * 1024 * 1024,
];
