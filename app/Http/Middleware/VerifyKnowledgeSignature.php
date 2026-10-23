<?php

namespace App\Http\Middleware;

use App\Support\Knowledge\Signature;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Accepts only a request signed with the Verified library's Brain secret (see Signature). Fail closed: with no secret
 * configured nobody can sign, so every request is refused. The refusal is the same for a missing, stale or wrong
 * signature, so a caller learns nothing about which part was wrong.
 */
class VerifyKnowledgeSignature
{
    public function handle(Request $request, Closure $next)
    {
        $secret = (string) config('knowledge.brain_secret');

        if ($secret === '') {
            Log::warning('knowledge.candidates_refused', ['reason' => 'KNOWLEDGE_SECRET_BRAIN is not set']);

            return $this->refuse();
        }

        $valid = Signature::valid(
            $secret,
            $request->header(Signature::TIMESTAMP_HEADER),
            $request->header(Signature::SIGNATURE_HEADER),
            $request->getContent(),
            (int) config('knowledge.signature_window', 300),
        );

        return $valid ? $next($request) : $this->refuse();
    }

    private function refuse()
    {
        return response()->json(['code' => 'invalid_signature', 'message' => 'The request is not signed correctly, or its timestamp is too old.'], 401);
    }
}
