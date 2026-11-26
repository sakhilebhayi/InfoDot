<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Moderator-only pages. A signed-out visitor never gets here (the auth middleware sends them to sign in first).
 * R-72 (S-02): the role works only with two-factor sign-in on. Someone who holds it without two-factor is sent to turn it on.
 */
class EnsureModerator
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user !== null && $user->hasModeratorRole() && ! $user->hasSecuredSignIn()) {
            return RequireTwoFactorForRole::refuse($request, 'moderation');
        }

        abort_unless($user?->isModerator(), 403, 'Only moderators can open this page.');

        return $next($request);
    }
}
