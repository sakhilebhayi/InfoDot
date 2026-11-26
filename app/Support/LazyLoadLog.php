<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * R-72 (P-27): on the live site a relation loaded one row at a time (an N+1) is written to the log, once per model and relation in
 * a request, so it can be found and fixed. Nothing is refused, so no page breaks because of it.
 */
final class LazyLoadLog
{
    public function __invoke(Model $model, string $relation): void
    {
        $key = 'lazy-load.'.$model::class.'.'.$relation;
        $request = request();

        if ($request->attributes->has($key)) {
            return;
        }

        $request->attributes->set($key, true);
        Log::warning('performance.lazy_load', ['category' => 'performance', 'model' => $model::class, 'relation' => $relation, 'route' => $request->route()?->getName()]);
    }
}
