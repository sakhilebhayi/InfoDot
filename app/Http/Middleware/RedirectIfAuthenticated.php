<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use App\Services\Sso\PlatformContext;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class RedirectIfAuthenticated
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response|RedirectResponse)  $next
     * @param  string|null  ...$guards
     * @return Response|RedirectResponse
     */
    public function handle(Request $request, Closure $next, ...$guards)
    {
        $guards = empty($guards) ? [null] : $guards;

        foreach ($guards as $guard) {
            if (Auth::guard($guard)->check()) {
                // R-54: someone already signed in who lands on sign-in or registration while a platform's request is
                // waiting simply continues that request (the address is validated: our own endpoint, a registered client).
                $pending = $request->hasSession() ? app(PlatformContext::class)->pendingUrl() : null;

                return redirect($pending ?? RouteServiceProvider::HOME);
            }
        }

        return $next($request);
    }
}
