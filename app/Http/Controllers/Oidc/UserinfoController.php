<?php

namespace App\Http\Controllers\Oidc;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * OIDC userinfo. `sub` is the ecosystem uuid — the same subject the id_token carries — and the
 * other claims are released only for the scopes the access token was actually granted.
 */
class UserinfoController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user('oidc');

        // The User model carries Sanctum's HasApiTokens; Passport's guard attaches the access
        // token through the same withAccessToken() hook, so it is read back via currentAccessToken().
        $token = $user->currentAccessToken();
        $scopes = $token->scopes ?? [];

        $claims = ['sub' => (string) $user->uuid];

        if (in_array('profile', $scopes, true)) {
            $claims['name'] = $user->name;
        }

        if (in_array('email', $scopes, true)) {
            $claims['email'] = $user->email;
            $claims['email_verified'] = $user->email_verified_at !== null;
        }

        return response()->json($claims);
    }
}
