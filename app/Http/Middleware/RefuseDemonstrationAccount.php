<?php

namespace App\Http\Middleware;

use App\Services\Demo\DemoRegistry;
use Closure;
use Illuminate\Http\Request;

/**
 * Keeps demonstration accounts (R-49) away from money: the routes that set up a payout, accept the seller terms or
 * spend Dot Points. A demonstration account is a fictional person, so nothing may be paid to it or billed for it.
 */
class RefuseDemonstrationAccount
{
    public function __construct(private readonly DemoRegistry $demo) {}

    public function handle(Request $request, Closure $next)
    {
        abort_if($this->demo->isDemoUser($request->user()), 403, 'Demonstration accounts cannot be paid, billed or redeem Dot Points.');

        return $next($request);
    }
}
