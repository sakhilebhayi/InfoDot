<?php

namespace App\Services\Ux;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Counts friction signals, nothing else. The event types and statuses are the ones in the Dot.Brain contract
 * (services/ux-interactions); `record()` refuses anything outside them, so a typo cannot create a new kind of data.
 * It never throws: a counter must not break a page.
 */
class UxSignals
{
    public const TYPES = [
        'nav.view' => ['success', 'failure'],
        'form.submit' => ['success', 'failure'],
        'form.validation_failed' => ['failure'],
        'search.run' => ['success'],
        'search.empty' => ['failure'],
        'request.failed' => ['failure'],
        'request.slow' => ['partial'],
    ];

    public const ERRORS = ['', 'validation', 'not_found', 'permission', 'rate_limited', 'server', 'timeout'];

    public const BUCKETS = ['', 'lt_3s', 'gte_3s'];

    private const ROUTE = '/\A\/[A-Za-z0-9_\-\/{}.:]{0,119}\z/';

    public function enabled(): bool
    {
        return (bool) config('ux.enabled');
    }

    /** The route's own template ("/question/{qid}"), never the address that was opened. Null when there is nothing safe to count. */
    public function templateOf(Request $request): ?string
    {
        $route = $request->route();
        if ($route === null) {
            return '/{unmatched}'; // an address no route answers: counted as a group, the address itself is dropped
        }

        $uri = '/'.ltrim(str_replace('?}', '}', $route->uri()), '/');

        if ($uri === '/up' || str_starts_with($uri, '/livewire') || str_starts_with($uri, '/build') || ! preg_match(self::ROUTE, $uri)) {
            return null;
        }

        return $uri;
    }

    public function record(string $type, string $route, string $status, string $error = '', string $bucket = ''): bool
    {
        if (! $this->enabled()
            || ! isset(self::TYPES[$type]) || ! in_array($status, self::TYPES[$type], true)
            || ! in_array($error, self::ERRORS, true) || ! in_array($bucket, self::BUCKETS, true)
            || ! preg_match(self::ROUTE, $route)) {
            return false;
        }

        try {
            DB::table('ux_daily_counts')->upsert(
                [['day' => now()->toDateString(), 'route' => $route, 'type' => $type, 'status' => $status, 'error' => $error, 'bucket' => $bucket, 'hits' => 1]],
                ['day', 'route', 'type', 'status', 'error', 'bucket'],
                ['hits' => DB::raw('hits + 1')],
            );
        } catch (Throwable $e) {
            report($e);

            return false;
        }

        return true;
    }

    public function prune(?int $days = null): int
    {
        $days = max(7, $days ?? (int) config('ux.retention_days', 180));

        return DB::table('ux_daily_counts')->where('day', '<', now()->subDays($days)->toDateString())->delete();
    }
}
