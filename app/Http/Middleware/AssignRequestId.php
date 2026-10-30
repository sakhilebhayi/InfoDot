<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Sentry\SentrySdk;
use Sentry\State\Scope;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every request an id that appears on every log line it writes, on the Sentry event, and in the X-Request-Id response header, so
 * an error report, a log line and a person's complaint can be matched. A caller's id (a platform, a monitor) is used when it is plain;
 * anything else is replaced. The id is random: it carries nothing about the person.
 */
class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $given = (string) $request->headers->get('X-Request-Id', '');
        $id = preg_match('/^[A-Za-z0-9._-]{8,64}$/', $given) === 1 ? $given : (string) Str::uuid();

        Log::shareContext(['request_id' => $id]);

        if (class_exists(SentrySdk::class)) {
            \Sentry\configureScope(fn (Scope $scope) => $scope->setTag('request_id', $id));
        }

        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);

        return $response;
    }
}
