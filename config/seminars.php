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
    'removal_appeal_days' => (int) env('SEMINARS_REMOVAL_APPEAL_DAYS', 30),   // a host can appeal a moderator's removal within this many days

    /*
    | How long records are kept (php artisan seminars:prune). Owner decisions of 2026-10-29. `money_years` is a proposal the owner's adviser must
    | confirm against the record-keeping periods that apply; set it to 0 to switch that part off until then.
    */
    'retention' => [
        'money_years' => (int) env('SEMINARS_MONEY_RETENTION_YEARS', 5),       // money records of people whose account is gone, after the last payment or invoice
        'room_days' => (int) env('SEMINARS_ROOM_RETENTION_DAYS', 30),          // small-room peers, messages, chat and room bans, after the seminar ended
        'points_outbox_days' => (int) env('SEMINARS_POINTS_OUTBOX_RETENTION_DAYS', 30),   // sent Dot Points rows
        'reports_months' => (int) env('SEMINARS_REPORTS_RETENTION_MONTHS', 12), // closed reports about seminars and appeals that were decided
    ],

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
        // The host agreement for chargeback recovery. Nothing takes money from a host until the owner's adviser has approved the wording in
        // resources/markdown/host-agreement.md and the owner sets APPROVED to true. The version must equal Dot.Billing's
        // BILLING_MARKETPLACE_AGREEMENT_VERSION; changing the wording means a new version that every host must accept again.
        'agreement_version' => env('SEMINARS_HOST_AGREEMENT_VERSION', 'v1'),
        'agreement_approved' => (bool) env('SEMINARS_HOST_AGREEMENT_APPROVED', false),
        // Pauses NEW ticket sales only. Refunds, Billing's callbacks and tickets already paid keep working. Set false to stop sales in an incident
        // without the side effects of SEMINARS_PAID_ENABLED=false.
        'checkout_enabled' => (bool) env('SEMINARS_CHECKOUT_ENABLED', true),

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

    /*
    | Dot Points for hosting (R-13, owner decision 2026-10-29). OFF until SEMINARS_POINTS_ENABLED. A host earns points once, when a
    | seminar they ran has finished, the `wait_hours` for late ratings have passed, and it had enough attendees and ratings. Only
    | people OUTSIDE every company the host owns or belongs to are counted, and the ratings counted are theirs alone, so a host's
    | own colleagues cannot make a seminar qualify. Attending earns nothing; free and paid seminars both count; cancelled ones never
    | do (and an award is reversed if the seminar is cancelled afterwards). Dopemine checks the same thresholds itself and refuses
    | anything below them. `consider_days` only bounds how far back the scheduled check looks.
    */
    'points' => [
        'enabled' => (bool) env('SEMINARS_POINTS_ENABLED', false),
        'min_attendees' => 3,
        'min_ratings' => 3,
        'min_average' => 4.0,
        'wait_hours' => 24,
        'consider_days' => 30,
    ],

    /*
    | Seminar video: InfoDot's own room on a LiveKit server YOU run (open source; see docs/programme/R13_LIVEKIT_SERVER.md). OFF until
    | SEMINARS_VIDEO_ENABLED and the three LIVEKIT_ values are set. Never claim live video to people until a real test on two networks passes.
    | InfoDot hands each registered person a short-lived join token, accepts LiveKit's signed webhook to record who really joined, and lets
    | the host remove a person. No recording is made.
    */
    'video' => [
        'enabled' => (bool) env('SEMINARS_VIDEO_ENABLED', false),
        'url' => env('LIVEKIT_URL'),                    // wss://live.example.org (the browser connects here)
        'api_key' => env('LIVEKIT_API_KEY'),
        'api_secret' => env('LIVEKIT_API_SECRET'),
        'token_minutes_after_end' => 15,                // a token is never valid beyond the seminar's end plus this
        'token_ttl_minutes' => (int) env('SEMINARS_VIDEO_TOKEN_TTL_MINUTES', 10),   // and never for longer than this: it cannot be revoked, so it is short
        'max_participants' => (int) env('SEMINARS_VIDEO_MAX_PARTICIPANTS', 100),
    ],

    /*
    | Small peer-to-peer rooms: video between browsers with NO media server. The host's own internet connection is the limit, so the host runs a
    | short upload test when setting a seminar up and is told exactly how many people it can hold. Viewers beyond the host's direct connections
    | are fed by other viewers (a relay tree), which stretches the number but can only be estimated, so the figure shown is deliberately cautious.
    | Browsers find each other through STUN servers (they only learn their own public address; no video passes through them). The default is a
    | public one; set your own (coturn) to depend on nobody. OFF until SEMINARS_P2P_ENABLED.
    */
    'p2p' => [
        'enabled' => (bool) env('SEMINARS_P2P_ENABLED', false),
        'stun_urls' => array_values(array_filter(array_map('trim', explode(',', (string) env('P2P_STUN_URLS', 'stun:stun.l.google.com:19302'))))),
        'turn' => ['url' => env('P2P_TURN_URL'), 'username' => env('P2P_TURN_USERNAME'), 'credential' => env('P2P_TURN_CREDENTIAL')],
        'max_viewers' => (int) env('SEMINARS_P2P_MAX_VIEWERS', 200),
        'max_depth' => 4,                 // host, then viewers relaying to viewers, at most this many hops below the host
        'host_uplink_share' => 0.7,       // the share of the measured upload the room may use
        'safety_factor' => 0.6,           // the number promised is this share of what the model says is possible
        'relay_slots' => (int) env('SEMINARS_P2P_RELAY_SLOTS', 3),   // how many others a relaying viewer feeds
        'peer_timeout_seconds' => 30,
        'speedtest_max_bytes' => 3_000_000,
        // Per quality: bitrate in kbps (audio included), how many viewers the HOST can feed directly (its processor encodes once per viewer),
        // and the share of viewers able to pass the stream on.
        'qualities' => [
            'audio' => ['label' => 'Audio only', 'kbps' => 40, 'host_direct_max' => 30, 'relay_ratio' => 0.9],
            'low' => ['label' => 'Video, standard (360p)', 'kbps' => 490, 'host_direct_max' => 6, 'relay_ratio' => 0.5],
            'medium' => ['label' => 'Video, sharp (720p)', 'kbps' => 1240, 'host_direct_max' => 4, 'relay_ratio' => 0.3],
        ],
    ],

    /*
    | Checking the meeting link a host types. A mistyped or made-up link is the commonest reason a seminar cannot be joined, so before it is
    | accepted InfoDot checks that it is a well-formed https link, that it matches the pattern of the well-known services (Google Meet, Zoom,
    | Teams, Whereby, Jitsi) when it claims to be one, that the address exists, and that the page answers. Clearly broken links are refused; links
    | that cannot be confirmed (the service blocks automated checks) are allowed with a warning. The check never contacts a private address.
    */
    'link_check' => [
        'enabled' => (bool) env('SEMINARS_LINK_CHECK', true),
        'timeout_seconds' => 4,
        'max_redirects' => 3,
    ],
];
