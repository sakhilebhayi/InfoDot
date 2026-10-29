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

    /*
    | Paid seminars. OFF until SEMINARS_PAID_ENABLED. The host sets the ticket price; InfoDot keeps `platform_percent` of it and the host
    | carries Paystack's processing fee. All money moves through Dot.Billing's Paystack split payments (InfoDot never touches a card or a bank
    | number beyond passing a host's account details once to Billing, which keeps only Paystack's subaccount code and the last four digits).
    | A ticket counts only when Billing's verified callback says it was paid, never when the attendee's browser comes back.
    | `platform_percent` here is for showing the host their share; the amount actually taken is set in Dot.Billing (BILLING_MARKETPLACE_PLATFORM_PERCENT).
    */
    'paid' => [
        'enabled' => (bool) env('SEMINARS_PAID_ENABLED', false),
        'currency' => 'ZAR',
        'platform_percent' => (int) env('SEMINARS_PLATFORM_PERCENT', 10),
        'min_price_cents' => (int) env('SEMINARS_MIN_PRICE_CENTS', 500),
        'max_price_cents' => (int) env('SEMINARS_MAX_PRICE_CENTS', 5000000),
        'hold_minutes' => 30,                                   // a place is held this long while the attendee pays
        'callback_secret' => env('SEMINARS_BILLING_CALLBACK_SECRET'),
        'return_path' => '/seminars',                          // where Paystack sends the attendee back to (UI only)

        // Refunds when an attendee cancels: full refund up to this many hours before the start, none after. A host cancelling always refunds in full.
        'refund_hours_before' => (int) env('SEMINARS_REFUND_HOURS_BEFORE', 24),

        // Tax. The host is the seller; InfoDot invoices the host for its commission. NOT legal advice: confirm with an adviser before relying on it.
        'vat_rate_percent' => (int) env('SEMINARS_VAT_RATE', 15),
        'commission_includes_vat' => (bool) env('SEMINARS_COMMISSION_INCLUDES_VAT', true),  // the 10% is VAT-inclusive, so the host never pays more than 10%
        'issue_tax_invoices' => (bool) env('SEMINARS_ISSUE_TAX_INVOICES', false),            // receipts carry tax-invoice fields only when this is on
        'invoice_prefix' => env('SEMINARS_INVOICE_PREFIX', 'INF'),
        'issuer' => [                                            // InfoDot's own details on commission invoices; nothing is issued while blank
            'name' => env('SEMINARS_ISSUER_NAME'),
            'address' => env('SEMINARS_ISSUER_ADDRESS'),
            'vat_number' => env('SEMINARS_ISSUER_VAT_NUMBER'),
        ],
    ],
];
