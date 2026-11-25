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
     *   pending_per_business requests waiting for an answer one business may have at once (many accounts could otherwise hold its whole calendar)
     *   pending_new_account_share / new_account_hours  accounts younger than this many hours may hold at most this share of those waiting places, so a
     *                        batch of throwaway accounts cannot shut real customers out
     * mail_per_person_per_day  booking emails one person gets in a day; beyond it only the bell tells them
     */
    'limits' => ['active_per_business' => 3, 'new_per_business_day' => 3, 'new_per_day' => 10, 'pending_per_business' => 30, 'pending_new_account_share' => 0.5, 'new_account_hours' => 24],
    'mail_per_person_per_day' => 20,

    /*
     * Reminders and unanswered requests (R-63 phase 5).
     *   reminders.customer_hours / staff_hours  how long before a confirmed appointment each side is reminded (once per lead time; a booking made
     *                                           after a lead time has already passed gets no reminder for it)
     *   pending_hours                           a request nobody answers for this long expires and stops holding the time
     */
    'reminders' => ['customer_hours' => [24, 1], 'staff_hours' => [24]],
    'pending_hours' => 48,

    /*
     * Cost limits on the public calendar (R-63 phase 7). The calendar reads a business's whole schedule, so an anonymous crowd could make one small business
     * expensive. A month's statuses and a day's slots are kept for a few seconds (a booking still re-checks the time under its lock, so a slightly old
     * calendar can never double-book), and one business's calendar works out at most `team_views_per_minute` fresh answers a minute across all visitors (answers served from the kept copy are free).
     */
    'public' => ['cache_seconds' => (int) env('BOOKING_PUBLIC_CACHE_SECONDS', 20), 'team_views_per_minute' => 600],

    /** How long the personal details on a booking are kept after the appointment ends; then they are removed and the appointment itself stays (POPIA). */
    'retention_months' => 24,
];
