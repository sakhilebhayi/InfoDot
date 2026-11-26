<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Platform administrator pages (R-50). The role is granted only by `platform-admin:grant` on the host; being a moderator is not enough.
 * R-72 (S-02): the role works only with two-factor sign-in on. Someone who holds it without two-factor is sent to turn it on.
 */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user !== null && $user->hasPlatformAdminRole() && ! $user->hasSecuredSignIn()) {
            return RequireTwoFactorForRole::refuse($request, 'platform administration');
        }

        abort_unless($user?->can('administer-platforms'), 403, 'Only platform administrators can open this page.');

        return $next($request);
    }
}
