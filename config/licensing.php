<?php

/*
|--------------------------------------------------------------------------
| Organisation licensing (R-13)
|--------------------------------------------------------------------------
|
| A company (a team that is not someone's personal workspace) has a number of licensed user seats. The first `included_seats` are free (R-53:
| ONE, the owner's); every other person needs a paid licence. A seat is used by the owner and by every member who accepted; a pending
| invitation reserves a seat (so concurrent invitations cannot promise more places than exist) until it is accepted or cancelled.
|
| The free allowance is read in exactly one place (SeatLedger::freeAllowance). The cutover is this one setting: LICENCE_INCLUDED_SEATS.
| Rollback is the same setting (put the old number back); nothing is deleted either way. See docs/programme/r53/RUNBOOK.md.
|
| NOTHING HERE INVENTS A PRICE. `price` stays empty until the owner approves one; seats cannot be bought until a price is set AND Dot.Finance is
| connected (see docs/programme/R13_INSPECTION.md). Until then a company with all its free seats used is
| told so plainly and nothing is charged.
*/

return [
    // The free allowance per company: ONE licence, held by the owner. A company-level override (team_licences.included_seats, set by
    // `licence:grandfather` for companies that existed before R-53) beats this number until its `grandfathered_until` date.
    'included_seats' => (int) env('LICENCE_INCLUDED_SEATS', 1),

    // Days an existing company keeps its current headcount free after `licence:grandfather --apply` (owner decision D7).
    'grandfather_days' => (int) env('LICENCE_GRANDFATHER_DAYS', 90),

    // An invitation can be accepted for this many days after it is sent (or sent again). After that it holds no seat, and
    // `invitations:prune` removes it once it has been expired for `invitation_prune_days`.
    'invitation_days' => (int) env('LICENCE_INVITATION_DAYS', 14),
    'invitation_prune_days' => (int) env('LICENCE_INVITATION_PRUNE_DAYS', 30),

    // true: a pending invitation holds a seat until it is accepted, declined, cancelled or expired.
    'pending_invitations_reserve_a_seat' => (bool) env('LICENCE_INVITATIONS_RESERVE', true),

    // The approved price of one extra licence, in minor units (cents), excluding VAT. Nothing here is a default: with the values empty no
    // price is shown anywhere and nothing can be bought. The owner's approved figures are set in the environment, never in code.
    'price' => [
        'amount_minor_units' => env('LICENCE_SEAT_PRICE_MINOR'),          // per user per month
        'annual_amount_minor_units' => env('LICENCE_SEAT_PRICE_ANNUAL_MINOR'), // per user per year
        'currency' => env('LICENCE_CURRENCY'),
        'periods' => ['month', 'year'],

        // Volume bands (owner decision D3): marginal, applied to the company's total number of PAID licences. Each band prices the licences
        // from the previous band's end up to `up_to` at `percent` of the unit price; `up_to` null = no limit. These are percentages of a
        // configured price, not prices.
        'bands' => [
            ['up_to' => 5, 'percent' => 100],
            ['up_to' => 20, 'percent' => 90],
            ['up_to' => null, 'percent' => 80],
        ],
    ],

    // Above this many people in one company (owner included) licences are by custom agreement and cannot be bought online (D12).
    'max_online_users' => (int) env('LICENCE_MAX_ONLINE_USERS', 100),

    // A purchase that was started and never paid is closed after this many days. It never held a licence.
    'pending_batch_days' => (int) env('LICENCE_PENDING_BATCH_DAYS', 7),

    // How late a renewal confirmation may be before the batch stops counting, in minutes.
    'period_tolerance_minutes' => (int) env('LICENCE_PERIOD_TOLERANCE_MINUTES', 60),

    // Dot.Finance is the only place seats are bought and paid for (Paystack lives there). InfoDot only READS what a company is entitled to,
    // over Finance's signed server-to-server API, and never from a checkout redirect. Without a URL and a secret nothing is read and the
    // included seats apply.
    'finance' => [
        'url' => env('LICENCE_FINANCE_URL'),
        'platform' => env('LICENCE_FINANCE_PLATFORM', 'infodot'),
        'secret' => env('FINANCE_ENTITLEMENT_SECRET'),
        'timeout_seconds' => 3,
        'cache_seconds' => 300,
        'failure_backoff_seconds' => (int) env('LICENCE_FINANCE_FAILURE_BACKOFF', 30),   // after a failed read, do not ask again for this long

        // R-53 phase 3: the signed server-to-server call that starts a checkout for one purchase batch (POST, idempotent on batch uuid).
        'checkout_path' => env('LICENCE_FINANCE_CHECKOUT_PATH', '/api/licences/checkout'),
        'checkout_timeout_seconds' => (int) env('LICENCE_FINANCE_CHECKOUT_TIMEOUT', 8),
        // Cancelling or reducing a purchase has to reach Finance, or Paystack keeps charging. Finance has no such endpoint yet, so the owner
        // actions are OFF until it does (then set the path and switch this on). While off the screens say how to ask for it instead.
        'cancel_enabled' => (bool) env('LICENCE_FINANCE_CANCEL_ENABLED', false),
        'cancel_path' => env('LICENCE_FINANCE_CANCEL_PATH', '/api/licences/cancel'),
    ],

    // The success and failure pages never activate anything; they wait for Finance's signed confirmation. They refresh themselves this
    // often while waiting, and stop refreshing (and say it is taking longer) after this many minutes.
    'return_refresh_seconds' => (int) env('LICENCE_RETURN_REFRESH_SECONDS', 8),
    'return_wait_minutes' => (int) env('LICENCE_RETURN_WAIT_MINUTES', 10),

    // Reminders (phase 4). Days before a renewal, per interval; reminders while a failed payment or a shortfall is in its grace (days left);
    // and days after the freeze for companies on `grandfathered_until`.
    'reminders' => [
        'renewal_days' => ['month' => [3], 'year' => [30, 7]],
        'grace_days_left' => [14, 7, 1],
        'grandfather_offsets' => [0, 30, 60, 80, 89],
        'support_email' => env('LICENCE_SUPPORT_EMAIL'),
    ],

    // Whether a real payment backend is connected for seat purchases. Off until Dot.Finance (or the chosen provider) can charge and confirm.
    'finance_connected' => (bool) env('LICENCE_FINANCE_CONNECTED', false),

    // After a failed payment, how long a company keeps its seats before anything changes. Nobody is deleted either way.
    'grace_days' => (int) env('LICENCE_GRACE_DAYS', 14),
];
