<?php

/*
| Seminars (R-13). Any signed-in, verified person may host one: no tier or reputation is needed. Seminars are FREE for now; paid
| seminars wait on the Dot.Billing payment flow. InfoDot has no video server: the host supplies a link to a meeting room they run
| elsewhere, and InfoDot only shows that link to people who registered, inside the time window. No live video is claimed.
*/
return [
    'max_upcoming_per_host' => (int) env('SEMINARS_MAX_UPCOMING_PER_HOST', 5),
    'min_lead_minutes' => 60,          // a seminar must start at least this long after it is published
    'join_opens_minutes_before' => 15,
    'min_duration_minutes' => 15,
    'max_duration_minutes' => 480,
    'max_capacity' => 5000,
    'min_ratings_shown' => 3,           // a host's average is shown publicly only from this many ratings
];
