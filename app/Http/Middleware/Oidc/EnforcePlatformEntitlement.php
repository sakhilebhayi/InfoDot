<?php

namespace App\Http\Middleware\Oidc;

use App\Models\DotPlatform;
use App\Services\Platforms\PlatformAccess;
use App\Services\Platforms\PlatformRefusal;
use App\Support\Input;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * R-50: the OAuth door. A platform that signs people in through /oauth/authorize is matched to its catalogue key by
 * its registry slug, and the signed-in person's current company must be entitled to it. A refusal is the same
 * explanation page as a refused launch, shown here on InfoDot: nothing is redirected to the platform and no
 * authorisation code is issued. Runs after `auth` and `verified`, so there is always a person to judge.
 */
class EnforcePlatformEntitlement
{
    public function __construct(private readonly PlatformAccess $access, private readonly PlatformRefusal $refusal) {}

    public function handle(Request $request, Closure $next): Response
    {
        $platform = DotPlatform::findByClientId(Input::text($request->query('client_id')) ?: null);
        $user = $request->user();

        if ($platform === null || $user === null) {
            return $next($request);
        }

        // A slug that is not a registry key cannot be judged. It is let through, as before R-50, and said out loud:
        // `sso:platform register` refuses such a slug now, and ops:check counts any that predate that rule.
        if (! $this->access->governs((string) $platform->slug) && config('ecosystem.platforms.'.$platform->slug) === null) {
            Log::warning('sso.authorize_unjudged', ['reason' => 'slug_not_in_registry', 'slug' => $platform->slug]);
        }

        $decision = $this->access->decide($user, (string) $platform->slug);

        if ($decision->refused()) {
            Log::info('sso.authorize_rejected', ['reason' => 'not_entitled:'.$decision->reason, 'client_id' => $platform->oauth_client_id]);

            return $this->refusal->response($user, (string) $platform->slug, $decision);
        }

        return $next($request);
    }
}
