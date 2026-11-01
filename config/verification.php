<?php

/*
| Business verification (R-29 Part B). What InfoDot can honestly check today: documents reviewed
| by a person, control of an email address at the business's own website domain, and the
| applicant's authority by declaration and document. Nothing here talks to a company registry or
| sends SMS — neither is connected, and the badge never claims either.
*/

return [

    // How long a verification stays current before the business renews it.
    'valid_months' => (int) env('VERIFICATION_VALID_MONTHS', 12),

    // Owners are reminded this many days before it lapses.
    'renewal_warning_days' => (int) env('VERIFICATION_RENEWAL_WARNING_DAYS', 30),

    // Evidence of rejected or withdrawn applications is deleted after this many days (POPIA s14).
    // Evidence of a verified business is kept while it is verified and this long after it ends.
    'evidence_retention_days' => (int) env('VERIFICATION_EVIDENCE_RETENTION_DAYS', 365),

    // Approval needs at least this many confirmed checks, one of them a document review.
    'minimum_checks' => 2,

    'max_evidence_files' => 6,

    'email_code' => [
        'ttl_minutes' => 15,
        'attempts' => 5,
    ],

    // Mailboxes anyone can open: an address here proves nothing about owning a business.
    'generic_email_domains' => [
        'gmail.com', 'googlemail.com', 'outlook.com', 'hotmail.com', 'live.com', 'msn.com', 'yahoo.com', 'yahoo.co.za',
        'icloud.com', 'me.com', 'mac.com', 'aol.com', 'proton.me', 'protonmail.com', 'gmx.com', 'gmx.net', 'zoho.com',
        'mail.com', 'yandex.com', 'webmail.co.za', 'mweb.co.za', 'telkomsa.net', 'vodamail.co.za', 'iafrica.com', 'lantic.net',
    ],
];
