<?php

namespace App\Http\Middleware;

use App\Services\Ux\UxSignals;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Turns the outcome of a web request into at most one or two counts (R-12). Runs after the response is sent (terminate), so it
 * cannot slow or break the page. It reads the status, the route template and the time taken, and nothing from the request body.
 */
class RecordUxSignals
{
    public function __construct(private readonly UxSignals $signals) {}

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! $this->signals->enabled()) {
            return;
        }

        try {
            $route = $this->signals->templateOf($request);
            if ($route === null) {
                return;
            }

            $status = $response->getStatusCode();
            $read = in_array($request->method(), ['GET', 'HEAD'], true);

            if ($status === 404) {
                $this->signals->record('nav.view', $route, 'failure', 'not_found');
            } elseif ($status === 403) {
                $this->signals->record('nav.view', $route, 'failure', 'permission');
            } elseif ($status === 429) {
                $this->signals->record('request.failed', $route, 'failure', 'rate_limited');
            } elseif ($status >= 500) {
                $this->signals->record('request.failed', $route, 'failure', 'server');
            } elseif ($status === 422 || (! $read && $this->failedValidation($request))) {
                $this->signals->record('form.validation_failed', $route, 'failure', 'validation');
            } elseif ($read) {
                if ($status < 400) {
                    $this->signals->record('nav.view', $route, 'success');
                    $this->slow($route);
                }
            } elseif ($status < 400) {
                $this->signals->record('form.submit', $route, 'success');
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function failedValidation(Request $request): bool
    {
        return $request->hasSession() && $request->session()->has('errors') && $request->session()->get('errors')->any();
    }

    private function slow(string $route): void
    {
        if (! defined('LARAVEL_START')) {
            return;
        }

        $ms = (microtime(true) - LARAVEL_START) * 1000;
        if ($ms >= (int) config('ux.slow_ms', 1500)) {
            $this->signals->record('request.slow', $route, 'partial', '', $ms >= 3000 ? 'gte_3s' : 'lt_3s');
        }
    }
}
