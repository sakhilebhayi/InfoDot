<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Platforms\PlatformAccess;
use App\Support\EcosystemHandoff;
use App\Support\PlatformSecret;
use App\Support\TeamRoles;
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
 * the person must have a verified email, and (R-50) at least one of the person's
 * companies must be entitled to the platform; the answer marks each company with
 * `entitled`. Every refusal looks the same to the caller; the reason goes to the
 * log (never the token).
 */
class EcosystemRedeemController extends Controller
{
    public function __invoke(Request $request, PlatformAccess $access): JsonResponse
    {
        $input = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'platform' => ['required', 'string', 'max:40'],
        ]);

        $platform = $input['platform'];

        if (! PlatformSecret::proves($request, $platform)) {
            return $this->refuse($platform, 'service secret missing or wrong', 401);
        }

        if (! PlatformSecret::live($platform)) {
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

        if ($user->isSuspended()) {
            return $this->refuse($platform, 'account suspended');
        }

        // R-50: which of the person's companies may use this platform. The code is spent either way; a person with no
        // entitled company is refused like any other bad code, and the reason is in the log.
        $teams = $user->allTeams()->values();
        $decisions = $teams->map(fn ($team) => $access->decideFor($user, $team, $platform));

        if (! $decisions->contains->allowed) {
            return $this->refuse($platform, 'no entitled company for this platform ('.($decisions->map->reason->unique()->implode(', ') ?: 'no company').')');
        }

        return response()->json([
            // The platform the code was redeemed for, in the registry's spelling.
            'platform' => $platform,
            'user' => [
                'id' => $user->id,
                // The stable identifier a platform should key its local account on. An email address can change or be
                // reused; matching on it alone lets one person's platform account capture another's sign-in (R-48 S-18).
                'uuid' => $user->uuid,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified' => true,
            ],
            'teams' => $teams->map(fn ($team, $i) => [
                'id' => $team->id,
                'uuid' => $team->uuid,
                'name' => $team->name,
                'personal' => (bool) $team->personal_team,
                ...TeamRoles::payload($user->ownsTeam($team) ? 'owner' : ($user->teamRole($team)?->key)),
                // Whether this company may use the platform for this person, and the reason when it may not. A platform
                // syncs only the entitled ones.
                'entitled' => $decisions[$i]->allowed,
                'access' => $decisions[$i]->allowed ? null : $decisions[$i]->reason,
            ])->values(),
            'current_team_id' => $user->current_team_id,
            'current_team_uuid' => $user->currentTeam?->uuid,
        ]);
    }

    private function refuse(string $platform, string $reason, int $status = 403): JsonResponse
    {
        Log::warning('Ecosystem redeem refused.', ['platform' => $platform, 'reason' => $reason]);

        return response()->json(['error' => 'invalid_request'], $status);
    }
}
