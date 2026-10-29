<?php

/*
|--------------------------------------------------------------------------
| Organisation licensing (R-13)
|--------------------------------------------------------------------------
|
| A company (a team that is not someone's personal workspace) has a number of licensed user seats. The first `included_seats` are free; more
| are bought. A seat is used by the owner and by every member who accepted; a pending invitation reserves a seat (so concurrent invitations
| cannot promise more places than exist) until it is accepted or cancelled.
|
| NOTHING HERE INVENTS A PRICE. `price` stays empty until the owner approves one; seats cannot be bought until a price is set AND billing is
| connected (Dot.Billing has no payment processing yet: see docs/programme/R13_INSPECTION.md). Until then a company with all its free seats used is
| told so plainly and nothing is charged.
*/

return [
    'included_seats' => (int) env('LICENCE_INCLUDED_SEATS', 3),

    // true: a pending invitation holds a seat until it is accepted, declined or cancelled.
    'pending_invitations_reserve_a_seat' => (bool) env('LICENCE_INVITATIONS_RESERVE', true),

    // The approved price of one extra seat. Both must be set by the owner's pricing decision; they are never defaulted.
    'price' => [
        'amount_minor_units' => env('LICENCE_SEAT_PRICE_MINOR'),   // e.g. cents; empty until decided
        'currency' => env('LICENCE_CURRENCY'),                      // e.g. ZAR; empty until decided
        'periods' => ['month', 'year'],
    ],

    // Dot.Billing is the only place seats are bought and paid for (Paystack lives there). InfoDot only READS what a company is entitled to,
    // over Billing's signed server-to-server API, and never from a checkout redirect. Without a URL and a secret nothing is read and the
    // included seats apply.
    'billing' => [
        'url' => env('LICENCE_BILLING_URL'),
        'platform' => env('LICENCE_BILLING_PLATFORM', 'infodot'),
        'secret' => env('BILLING_ENTITLEMENT_SECRET'),
        'timeout_seconds' => 3,
        'cache_seconds' => 300,
    ],

    // Whether a real payment backend is connected for seat purchases. Off until Dot.Billing (or the chosen provider) can charge and confirm.
    'billing_connected' => (bool) env('LICENCE_BILLING_CONNECTED', false),

    // After a failed payment, how long a company keeps its seats before anything changes. Nobody is deleted either way.
    'grace_days' => (int) env('LICENCE_GRACE_DAYS', 14),
];
