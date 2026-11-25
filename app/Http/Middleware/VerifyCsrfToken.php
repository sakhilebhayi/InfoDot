<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        'notifications/unsubscribe/*',   // the one-click unsubscribe a mail client posts (RFC 8058): it has no session, and the signed address is what authorises it
        'csp-report',   // sent by the browser, not by a form; it carries no session and changes nothing
    ];
}
