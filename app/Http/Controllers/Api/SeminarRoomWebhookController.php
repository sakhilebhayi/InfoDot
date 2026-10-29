<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Seminar;
use App\Services\Seminars\LiveKit;
use App\Services\Seminars\SeminarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * LiveKit tells InfoDot who connected to a seminar room. Server to server: the Authorization header is a JWT signed with the LiveKit API
 * secret that carries the hash of the body, so a forged or altered call is refused. Only `participant_joined` matters; a person is marked
 * as having attended once, and the host and anyone who did not register are ignored.
 */
class SeminarRoomWebhookController extends Controller
{
    public function __invoke(Request $request, LiveKit $livekit, SeminarService $seminars): JsonResponse
    {
        if (! $livekit->configured() || ! $livekit->webhookIsValid($request->header('Authorization'), $request->getContent())) {
            return response()->json(['error' => 'invalid_request'], 401);
        }

        $body = json_decode($request->getContent(), true);

        if (! is_array($body) || ($body['event'] ?? null) !== 'participant_joined') {
            return response()->json(['status' => 'ignored']);
        }

        $room = (string) ($body['room']['name'] ?? '');
        $identity = (string) ($body['participant']['identity'] ?? '');

        if (! preg_match('/^seminar-(\d+)$/', $room, $m) || ! preg_match('/^u(\d+)$/', $identity, $u) || ! ($seminar = Seminar::find((int) $m[1]))) {
            return response()->json(['status' => 'ignored']);
        }

        return response()->json(['status' => $seminars->markJoinedFromRoom($seminar, (int) $u[1]) ? 'recorded' : 'ignored']);
    }
}
