<?php

/**
 * Notification delivery (R-64 phase 8). The daily summary email gathers what a person has not read, in the categories they chose to hear about by
 * email as a summary, into one message instead of one email for each event.
 */
return [
    'digest' => [
        /** A kill switch for the daily summary (NOTIFY_DIGEST=false stops it everywhere without a deploy of code). */
        'enabled' => (bool) env('NOTIFY_DIGEST', true),
        /** The most items listed under one category; the rest are counted ("and 4 more"). */
        'items_per_category' => 5,
        /** Nothing older than this many days goes into a summary, whatever the last send was. */
        'max_age_days' => 3,
    ],
];
