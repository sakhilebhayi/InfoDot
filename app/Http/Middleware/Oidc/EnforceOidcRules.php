<?php

namespace App\Http\Middleware\Oidc;

use App\Models\DotPlatform;
use App\Support\Input;
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
        $platform = DotPlatform::findByClientId(Input::text($request->query('client_id')) ?: null);

        $reason = self::rejectionReason($request->query(), $platform);

        if ($reason !== null) {
            Log::info('sso.authorize_rejected', ['reason' => $reason, 'client_id' => Input::text($request->query('client_id'))]);

            abort(400, 'This sign-in request was not accepted. The requesting platform must retry with a valid request.');
        }

        return $next($request);
    }

    /**
     * The house rules for an authorize request as a pure function, so the sign-in pages (R-54) judge a pending request
     * by exactly the same rules the endpoint does. Null means acceptable.
     *
     * @param  array<string, mixed>  $query
     */
    public static function rejectionReason(array $query, ?DotPlatform $platform): ?string
    {
        return match (true) {
            ! $platform => 'unregistered_client',
            ! $platform->isActive() => 'platform_suspended',
            ($query['response_type'] ?? null) !== 'code' => 'response_type_not_code',
            ! in_array('openid', preg_split('/[\s+]+/', Input::text($query['scope'] ?? null)) ?: [], true) => 'missing_openid_scope',
            strlen(Input::text($query['state'] ?? null)) < config('sso.min_state_length') => 'state_missing_or_short',
            strlen(Input::text($query['nonce'] ?? null)) < config('sso.min_nonce_length') => 'nonce_missing_or_short',
            ($query['code_challenge_method'] ?? null) !== 'S256' => 'pkce_method_not_s256',
            ! preg_match('/^[A-Za-z0-9\-._~]{43,128}$/', Input::text($query['code_challenge'] ?? null)) => 'pkce_challenge_invalid',
            default => null,
        };
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
