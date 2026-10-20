<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Dot.Dopemine hosts the Dot Points ledger. InfoDot reports contribution events to it (signed, retried from a
    // durable outbox) and reads a person's balance for their profile. Unset = events wait in the outbox.
    'dopemine' => [
        'url' => env('DOPEMINE_URL', 'https://dopemine.infodot.co.za'),
        'secret' => env('DOPEMINE_INGEST_SECRET'),
        'timeout' => (int) env('DOPEMINE_TIMEOUT', 5),
    ],

];
