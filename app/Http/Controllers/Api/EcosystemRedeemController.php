<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\EcosystemHandoff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Lets a Dot platform that has its own database prove a handoff token.
 *
 * The browser arrives at the satellite with ?token=...; the satellite cannot find
 * that token in its own database, so it calls this endpoint with its per-platform
 * secret. The token is single use, bound to the platform it was minted for, and
 * the person must have a verified email. Every refusal looks the same to the
 * caller; the reason goes to the log (never the token).
 */
class EcosystemRedeemController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $input = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['required', 'string', 'max:40'],
        ]);

        $platform = $input['platform'];
        $secret = (string) config('ecosystem.service_secrets.'.$platform, '');
        $bearer = (string) $request->bearerToken();

        if ($secret === '' || $bearer === '' || ! hash_equals($secret, $bearer)) {
            return $this->refuse($platform, 'service secret missing or wrong', 401);
        }

        $registry = config('ecosystem.platforms', []);
        if (! isset($registry[$platform]) || ($registry[$platform]['active'] ?? true) === false) {
            return $this->refuse($platform, 'platform not registered or not live');
        }

        $accessToken = PersonalAccessToken::findToken($input['token']);

        if (
            ! $accessToken
            || $accessToken->name !== EcosystemHandoff::tokenName($platform)
            || ! $accessToken->can('ecosystem:read')
            || ($accessToken->expires_at && $accessToken->expires_at->isPast())
        ) {
            return $this->refuse($platform, 'token unknown, wrong audience, wrong ability or expired');
        }

        $user = $accessToken->tokenable;

        // Single use: gone whatever happens next.
        $accessToken->delete();

        if (! $user instanceof User || $user->email_verified_at === null) {
            return $this->refuse($platform, 'user missing or email not verified');
        }

        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified' => true,
            ],
            'teams' => $user->allTeams()->map(fn ($team) => [
                'id' => $team->id,
                'name' => $team->name,
                'personal' => (bool) $team->personal_team,
                'role' => $user->ownsTeam($team) ? 'owner' : ($user->teamRole($team)?->key),
            ])->values(),
            'current_team_id' => $user->current_team_id,
        ]);
    }

    private function refuse(string $platform, string $reason, int $status = 403): JsonResponse
    {
        Log::warning('Ecosystem redeem refused.', ['platform' => $platform, 'reason' => $reason]);

        return response()->json(['error' => 'invalid_request'], $status);
    }
}
