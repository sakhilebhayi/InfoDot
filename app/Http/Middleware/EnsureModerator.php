<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Moderator-only pages. A signed-out visitor never gets here (the auth middleware sends them to sign in first). */
class EnsureModerator
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->isModerator(), 403, 'Only moderators can open this page.');

        return $next($request);
    }
}
