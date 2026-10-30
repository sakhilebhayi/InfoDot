<?php

namespace App\Http\Controllers\Oidc;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * OIDC discovery metadata (RFC 8414 / OpenID Connect Discovery 1.0). Written by hand instead of the
 * package's controller so it states exactly what this server enforces: code flow only, PKCE S256
 * only (EnforceOidcRules rejects `plain`), RS256 id_tokens, and the logout endpoints.
 */
class DiscoveryController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $issuer = rtrim(url('/'), '/');

        return response()->json([
            'issuer' => $issuer,
            'authorization_endpoint' => $issuer.'/oauth/authorize',
            'token_endpoint' => $issuer.'/oauth/token',
            'userinfo_endpoint' => $issuer.'/oauth/userinfo',
            'jwks_uri' => $issuer.'/oauth/jwks',
            'end_session_endpoint' => $issuer.'/oauth/end-session',
            'scopes_supported' => array_keys(config('openid.passport.tokens_can')),
            'response_types_supported' => ['code'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'token_endpoint_auth_methods_supported' => ['client_secret_basic', 'client_secret_post'],
            'code_challenge_methods_supported' => ['S256'],
            'claims_supported' => ['sub', 'iss', 'aud', 'exp', 'iat', 'nonce', 'name', 'email', 'email_verified'],
            'backchannel_logout_supported' => true,
            'backchannel_logout_session_supported' => false,
        ]);
    }
}
