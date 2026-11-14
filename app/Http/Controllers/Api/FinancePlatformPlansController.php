<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlatformBundle;
use App\Models\PlatformPlan;
use App\Support\Knowledge\Signature;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * GET /api/finance/platform-plans (R-50): what Dot.Finance may sell. Server to server, in the same scheme as the
 * confirmations Dot.Finance sends back: HMAC-SHA256 over "{timestamp}." (a GET signs the empty body) with the same
 * secret, inside a five minute window, compared in constant time. While the secret is unset every request is refused.
 *
 * Only approved plans and bundles that are on sale are returned, with the amounts a platform administrator approved
 * and nothing else: no company, no person, no draft. A plan that costs nothing is not sold, so it is left out, as the
 * confirmation endpoint would refuse a payment for it. The answer is the whole list, not a page: there are a few dozen
 * rows at most.
 */
class FinancePlatformPlansController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('platform_subscriptions.confirmation_secret');

        if (! Signature::valid($secret, $request->header(Signature::TIMESTAMP_HEADER), $request->header(Signature::SIGNATURE_HEADER), $request->getContent(), (int) config('platform_subscriptions.signature_window', 300))) {
            return response()->json(['error' => 'invalid_request'], 401);
        }

        $plans = PlatformPlan::query()->where('active', true)->whereNotNull('approved_at')->whereNotNull('amount_minor')->whereNotNull('currency')
            ->orderBy('platform_key')->orderBy('amount_minor')->orderBy('slug')->get()
            ->reject(fn (PlatformPlan $plan) => $plan->isFree())
            ->map(fn (PlatformPlan $plan) => [
                'slug' => $plan->slug,
                'platform' => $plan->platform_key,
                'name' => $plan->name,
                'amount_minor' => (int) $plan->amount_minor,
                'currency' => $plan->currency,
                'interval' => $plan->interval,
                'trial_days' => $plan->trial_days === null ? null : (int) $plan->trial_days,
            ])->values();

        $bundles = [];

        foreach (PlatformBundle::query()->with('items')->where('active', true)->whereNotNull('approved_at')->whereNotNull('amount_minor')->whereNotNull('currency')->orderBy('slug')->get() as $bundle) {
            $platforms = [];

            foreach ($bundle->items->sortBy('platform_key') as $item) {
                $platforms[] = ['platform' => $item->platform_key, 'plan' => $item->plan_slug];
            }

            $bundles[] = [
                'slug' => $bundle->slug,
                'name' => $bundle->name,
                'amount_minor' => (int) $bundle->amount_minor,
                'currency' => $bundle->currency,
                'interval' => $bundle->interval,
                'platforms' => $platforms,
            ];
        }

        return response()->json(['plans' => $plans, 'bundles' => $bundles])->header('Cache-Control', 'no-store');
    }
}
