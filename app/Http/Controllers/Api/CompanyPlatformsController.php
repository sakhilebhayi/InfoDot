<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlatformCatalogue;
use App\Models\PlatformFeature;
use App\Models\Team;
use App\Services\Platforms\PlatformAccess;
use App\Support\Input;
use App\Support\PlatformSecret;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Lets a Dot platform ask what a company holds (R-50): on its own sign-in path ("is this company still entitled to
 * me?") and for Dot.Analytics' "Your business platforms". Server to server, proved by the calling platform's own
 * secret, exactly as the code redemption is. The answer is about the company only: no person, no price, no plan.
 */
class CompanyPlatformsController extends Controller
{
    public function __invoke(Request $request, string $uuid, PlatformAccess $access): JsonResponse
    {
        $caller = Input::text($request->query('platform')) ?: Input::text($request->header('X-Dot-Platform'));

        if ($caller === '' || strlen($caller) > 40 || ! PlatformSecret::proves($request, $caller)) {
            return $this->refuse($caller, 'service secret missing or wrong', 401);
        }

        if (! PlatformSecret::live($caller)) {
            return $this->refuse($caller, 'platform not registered or not live');
        }

        $team = Team::where('uuid', $uuid)->first();

        if ($team === null) {
            return response()->json(['error' => 'not_found'], 404);
        }

        $features = PlatformFeature::where('platform_key', $caller)->orderBy('key')->get()->mapWithKeys(fn (PlatformFeature $f) => [$f->key => ['state' => $f->state, 'message' => $f->unavailableMessage()]])->all();

        $platforms = $access->catalogue()
            ->filter(fn ($entry) => $entry->discoverable && config('ecosystem.platforms.'.$entry->key) !== null && $access->governs($entry->key) || $entry->key === 'infodot')
            ->map(fn ($entry) => [
                'key' => $entry->key,
                'name' => $entry->name,
                'status' => $entry->key === 'infodot' ? 'included' : $access->businessState($team, $entry->key),
                'entitled' => $access->companyHolds($team, $entry->key),
                'is_core' => (bool) $entry->is_core,
                // R-61 availability contract: whether the platform may be used AT ALL right now, apart from what the company holds.
                // A platform in maintenance is `available: false` for everyone, whatever they have bought. InfoDot itself is always up here.
                'available' => $entry->key === 'infodot' ? true : $entry->isUsable(),
                'state' => $entry->key === 'infodot' ? PlatformCatalogue::OPERATIONAL : $entry->operational_status,
                'message' => $entry->key === 'infodot' ? null : $entry->unavailableMessage(),
                // How the company shares the caller's own platform among its people; nobody else's business.
                ...($entry->key === $caller ? ['access_mode' => $access->entitlement($team, $entry->key)->access_mode ?? 'everyone'] : []),
                // R-61: the caller's own switchable features and their state, for the caller only. A feature that is not listed is available.
                ...($entry->key === $caller ? ['features' => $features] : []),
            ])->values();

        return response()->json([
            'contract' => 2,
            'platform' => $caller,
            'company' => ['uuid' => $team->uuid, 'personal' => (bool) $team->personal_team],
            'platforms' => $platforms,
        ]);
    }

    private function refuse(string $platform, string $reason, int $status = 403): JsonResponse
    {
        Log::warning('Ecosystem platforms read refused.', ['platform' => mb_substr($platform, 0, 40), 'reason' => $reason]);

        return response()->json(['error' => 'invalid_request'], $status);
    }
}
