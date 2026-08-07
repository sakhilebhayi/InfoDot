<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class EcosystemTokenController extends Controller
{
    public function issue(Request $request): JsonResponse
    {
        $user = $request->user();

        $ttlMinutes = (int) config('ecosystem.handoff_ttl', 5);

        $token = $user->createToken(
            'ecosystem-handoff',
            ['ecosystem:read'],
            Carbon::now()->addMinutes($ttlMinutes)
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at->toISOString(),
        ]);
    }
}
