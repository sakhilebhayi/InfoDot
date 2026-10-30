<?php

namespace App\Http\Middleware\Oidc;

use App\Models\DotPlatform;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * R-20 §7: the identity provider's own house rules, enforced before Passport/league ever sees the
 * request. On authorize: the client must be a registered, active platform; the flow must be code +
 * PKCE S256 with real state and nonce and the openid scope. On token: a suspended platform's secret
 * stops working at once. Failures answer with a safe error and a log line (directive-level only,
 * never a token or secret) and are NEVER redirected to the requested redirect_uri.
 */
class EnforceOidcRules
{
    public function handle(Request $request, Closure $next): Response
    {
        return $request->isMethod('GET')
            ? $this->enforceAuthorize($request, $next)
            : $this->enforceToken($request, $next);
    }

    private function enforceAuthorize(Request $request, Closure $next): Response
    {
        $platform = DotPlatform::findByClientId($request->query('client_id'));

        $reason = match (true) {
            ! $platform => 'unregistered_client',
            ! $platform->isActive() => 'platform_suspended',
            $request->query('response_type') !== 'code' => 'response_type_not_code',
            ! in_array('openid', preg_split('/[\s+]+/', (string) $request->query('scope')) ?: [], true) => 'missing_openid_scope',
            strlen((string) $request->query('state')) < config('sso.min_state_length') => 'state_missing_or_short',
            strlen((string) $request->query('nonce')) < config('sso.min_nonce_length') => 'nonce_missing_or_short',
            $request->query('code_challenge_method') !== 'S256' => 'pkce_method_not_s256',
            ! preg_match('/^[A-Za-z0-9\-._~]{43,128}$/', (string) $request->query('code_challenge')) => 'pkce_challenge_invalid',
            default => null,
        };

        if ($reason !== null) {
            Log::info('sso.authorize_rejected', ['reason' => $reason, 'client_id' => (string) $request->query('client_id')]);

            abort(400, 'This sign-in request was not accepted. The requesting platform must retry with a valid request.');
        }

        return $next($request);
    }

    private function enforceToken(Request $request, Closure $next): Response
    {
        $clientId = $request->input('client_id') ?? $request->getUser();

        $platform = DotPlatform::findByClientId(is_string($clientId) ? $clientId : null);

        if ($platform && ! $platform->isActive()) {
            Log::info('sso.token_rejected', ['reason' => 'platform_suspended', 'client_id' => $clientId]);

            return response()->json(['error' => 'invalid_client', 'error_description' => 'Client authentication failed'], 401);
        }

        return $next($request);
    }
}
