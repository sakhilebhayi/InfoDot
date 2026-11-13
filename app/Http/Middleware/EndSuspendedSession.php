<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Accounts\AccountSuspension;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ends the browser session of a suspended account on its next request. Suspending deletes the stored sessions, but
 * that only reaches the database session driver; this catches every other case. It reads the person the session
 * middleware has already loaded, so guests and everyone else pass through without a query.
 */
class EndSuspendedSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('web')->user();

        if (! $user instanceof User || ! $user->isSuspended()) {
            return $next($request);
        }

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => AccountSuspension::MESSAGE], 401);
        }

        return redirect()->route('login')->withErrors(['email' => AccountSuspension::MESSAGE]);
    }
}
