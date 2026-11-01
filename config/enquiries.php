<?php

/*
 * R-33 Phase 3: the InfoDot Inbox. Limits that keep enquiries useful and stop them being used to
 * spam businesses. Each one is a plain number a business owner could be told.
 */
return [
    // New enquiries one person may open in 24 hours, across all businesses, and with one business.
    'per_day' => (int) env('ENQUIRIES_PER_DAY', 10),
    'per_business_per_day' => (int) env('ENQUIRIES_PER_BUSINESS_PER_DAY', 2),

    // The same words sent twice inside this many hours are refused as a duplicate.
    'duplicate_hours' => 24,

    'min_length' => 20,
    'max_length' => 5000,
    'max_files' => 3,

    // At most one email per person per conversation in this many minutes; the bell still shows each message.
    'email_quiet_minutes' => 30,

    'categories' => [
        'general' => 'A general question',
        'quote' => 'A quote or pricing',
        'product' => 'A product or service',
        'support' => 'Help with something you bought',
        'partnership' => 'Working together',
        'other' => 'Something else',
    ],
];
