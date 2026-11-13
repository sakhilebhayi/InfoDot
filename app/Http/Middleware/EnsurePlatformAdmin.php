<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Platform administrator pages (R-50). The role is granted only by `platform:admin grant` on the host; being a moderator is not enough. */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user()?->isPlatformAdmin(), 403, 'Only platform administrators can open this page.');

        return $next($request);
    }
}
