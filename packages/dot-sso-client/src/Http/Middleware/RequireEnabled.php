<?php

namespace Dot\SsoClient\Http\Middleware;

use Closure;
use Dot\SsoClient\Support\Sso;
use Illuminate\Http\Request;

/** While DOTSSO_ENABLED is false the package's routes do not exist as far as anyone can tell. */
class RequireEnabled
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(Sso::enabled(), 404);

        return $next($request);
    }
}
