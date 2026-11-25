<?php

/** Appointment booking (R-63): the short lists the setup screens offer. Amounts are information only; nothing is charged by booking. */
return [
    /** @var array<string, string> code => label */
    'currencies' => ['ZAR' => 'South African rand (ZAR)', 'USD' => 'US dollar (USD)', 'EUR' => 'Euro (EUR)', 'GBP' => 'British pound (GBP)'],

    /*
     * Limits on one customer (R-63 phase 3). A pending booking holds its time, so without a cap one account could hold a business's whole calendar.
     *   active_per_business  appointments (pending or confirmed, not yet started) one person may hold with one business
     *   new_per_business_day new bookings one person may make with one business in 24 hours
     *   new_per_day          new bookings one person may make anywhere in 24 hours
     */
    'limits' => ['active_per_business' => 3, 'new_per_business_day' => 3, 'new_per_day' => 10],
];
