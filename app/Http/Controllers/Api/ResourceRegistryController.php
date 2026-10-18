<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Resource;
use App\Models\Team;
use App\Support\Reference;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Lets the platform that OWNS an object register a pointer to it, so InfoDot can open it
 * with a stable reference. Server to server, authenticated by that platform's secret
 * (the same one it redeems handoff tokens with). A platform can only register types it is
 * authoritative for, only for real companies, and only ever its own pointers.
 */
class ResourceRegistryController extends Controller
{
    /** platform => object types it may register */
    private const AUTHORITY = ['files' => ['file'], 'docs' => ['document']];

    public function store(Request $request): JsonResponse
    {
        $platform = $this->platform($request);

        if ($platform === null) {
            return $this->refuse(401);
        }

        $data = $request->validate([
            'team_uuid' => ['required', 'uuid'],
            'type' => ['required', 'string', 'max:20'],
            'object_uuid' => ['required', 'string', 'max:64'],
            'path' => ['required', 'string', 'max:255', 'regex:#^/(?!/)(?!.*\.\.)[A-Za-z0-9/_\-.]*$#'],
        ]);

        if (! in_array($data['type'], self::AUTHORITY[$platform] ?? [], true)) {
            return $this->refuse(403, $platform, 'platform is not authoritative for that type');
        }

        $team = Team::where('uuid', $data['team_uuid'])->where('personal_team', false)->first();

        if (! $team) {
            return $this->refuse(422, $platform, 'unknown company');
        }

        $resource = Resource::updateOrCreate(
            ['platform' => $platform, 'platform_object_uuid' => $data['object_uuid']],
            ['team_id' => $team->id, 'type' => $data['type'], 'path' => $data['path']],
        );
        $resource->setRelation('team', $team);

        return response()->json([
            'uuid' => $resource->uuid,
            'reference' => Reference::for($resource),
            'url' => route('resources.open', ['resource' => $resource->uuid]),
        ], $resource->wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(Request $request, string $objectUuid): JsonResponse
    {
        $platform = $this->platform($request);

        if ($platform === null) {
            return $this->refuse(401);
        }

        // Scoped to the caller's own platform: one platform can never remove another's pointers.
        Resource::where('platform', $platform)->where('platform_object_uuid', $objectUuid)->delete();

        return response()->json(['ok' => true]);
    }

    private function platform(Request $request): ?string
    {
        $platform = (string) $request->input('platform', $request->query('platform', ''));
        $secret = (string) config('ecosystem.service_secrets.'.$platform, '');
        $bearer = (string) $request->bearerToken();

        return $secret !== '' && $bearer !== '' && hash_equals($secret, $bearer) ? $platform : null;
    }

    private function refuse(int $status, ?string $platform = null, string $reason = 'service secret missing or wrong'): JsonResponse
    {
        Log::warning('Resource registry call refused.', ['platform' => $platform, 'reason' => $reason]);

        return response()->json(['error' => 'invalid_request'], $status);
    }
}
