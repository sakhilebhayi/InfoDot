<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\EcosystemHandoff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class EcosystemTokenController extends Controller
{
    public function issue(Request $request): JsonResponse
    {
        $user = $request->user();

        // Optional audience: a platform key from the registry. Without it the token
        // can only be redeemed by an app that shares this database.
        $data = $request->validate(['platform' => ['nullable', 'string', 'in:'.implode(',', array_keys(config('ecosystem.platforms', [])))]]);

        $ttlMinutes = (int) config('ecosystem.handoff_ttl', 5);

        $token = $user->createToken(
            EcosystemHandoff::tokenName($data['platform'] ?? null),
            ['ecosystem:read'],
            Carbon::now()->addMinutes($ttlMinutes)
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at->toISOString(),
        ]);
    }
}
