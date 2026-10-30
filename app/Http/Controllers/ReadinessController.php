<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * GET /ready: can this instance do its job? /up only says the framework booted. This also asks the database, the cache and the storage
 * disk. The answer is "ok" or "degraded" and nothing else: which check failed goes to the log, never to the caller, so the endpoint is safe
 * to leave open for an uptime monitor.
 */
class ReadinessController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => fn () => DB::select('select 1'),
            'cache' => function () {
                $key = 'ready:'.bin2hex(random_bytes(4));
                Cache::put($key, '1', 10);
                abort_unless(Cache::pull($key) === '1', 500);
            },
            'storage' => fn () => abort_unless(is_writable(storage_path('framework')) && is_writable(storage_path('logs')), 500),
        ];

        $failed = [];

        foreach ($checks as $name => $check) {
            try {
                $check();
            } catch (Throwable $e) {
                $failed[] = $name;
                Log::error('readiness check failed', ['check' => $name, 'exception' => $e::class]);
            }
        }

        return response()->json(['status' => $failed === [] ? 'ok' : 'degraded'], $failed === [] ? 200 : 503)->header('Cache-Control', 'no-store');
    }
}
