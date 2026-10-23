<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Library\CandidateFeed;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * GET /api/knowledge/candidates?since=<ISO 8601>&limit=100 : the public, community-corroborated answers and solutions
 * that Dot.Brain's recommender may consider for the Verified library, as references and facts only (see CandidateFeed).
 * Server to server; the route's `knowledge.signed` middleware has already checked the signature.
 */
class KnowledgeCandidatesController extends Controller
{
    public function __invoke(Request $request, CandidateFeed $feed): JsonResponse
    {
        $default = (int) config('knowledge.candidates.default_limit', 100);
        $max = (int) config('knowledge.candidates.max_limit', 200);

        $limit = $request->query('limit', $default);
        if (is_array($limit) || ! preg_match('/\A\d{1,6}\z/', (string) $limit) || (int) $limit < 1 || (int) $limit > $max) {
            return $this->invalid("limit must be a whole number from 1 to {$max}.");
        }

        $since = null;
        $raw = $request->query('since');
        if ($raw !== null && $raw !== '') {
            // A date with an explicit offset ("Z" or "+02:00"); a bare local time would be ambiguous.
            if (! is_string($raw) || ! preg_match('/\A\d{4}-\d{2}-\d{2}T\d{2}:\d{2}(:\d{2}(\.\d{1,6})?)?(Z|[+-]\d{2}:?\d{2})\z/', $raw)) {
                return $this->invalid('since must be an ISO 8601 date and time with an offset, for example 2026-10-27T08:00:00Z.');
            }

            try {
                $since = Carbon::parse($raw);
            } catch (InvalidFormatException) {
                return $this->invalid('since must be an ISO 8601 date and time with an offset, for example 2026-10-27T08:00:00Z.');
            }
        }

        return response()->json($feed->page($since, (int) $limit))->header('Cache-Control', 'no-store');
    }

    private function invalid(string $message): JsonResponse
    {
        return response()->json(['code' => 'invalid_request', 'message' => $message], 422);
    }
}
