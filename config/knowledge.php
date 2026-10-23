<?php

/*
|--------------------------------------------------------------------------
| The Verified library and the Dot.Brain recommender (R-9 section 5)
|--------------------------------------------------------------------------
| A moderator-curated list of answers and solutions that the community has already corroborated. Dot.Brain runs a
| rules-only recommender (no LLM) that PULLS candidates from InfoDot and writes its recommendations to Dot.Memory;
| InfoDot reads them back for the moderators and reports each moderator decision to Memory. Brain recommends,
| moderators decide.
|
| Both directions are server to server, signed with HMAC-SHA256 over "{timestamp}.{raw body}" (a GET signs the empty
| string) in the headers X-InfoDot-Timestamp and X-InfoDot-Signature ("sha256=" + hex), inside a 300 second window.
| Two separate secrets, one per direction. Never committed, never logged, never shown.
*/

return [

    // Dot.Brain -> InfoDot (GET /api/knowledge/candidates). Same value as KNOWLEDGE_SECRET_BRAIN in Brain's secrets.
    // Unset = the endpoint refuses every request (fail closed).
    'brain_secret' => env('KNOWLEDGE_SECRET_BRAIN'),

    // InfoDot -> Dot.Memory (recommendations and outcomes). Same value as KNOWLEDGE_SECRET_INFODOT on Dot.Memory.
    // Unset = the moderators' "Recommended" tab says Memory is not connected, and no outcome is sent (it waits in the outbox).
    'infodot_secret' => env('KNOWLEDGE_SECRET_INFODOT'),

    'memory_url' => env('MEMORY_URL', 'https://memory.infodot.co.za'),

    'timeout' => (int) env('KNOWLEDGE_TIMEOUT', 5),

    // How far a request's timestamp may be from ours, in seconds (both directions).
    'signature_window' => 300,

    'candidates' => [
        'default_limit' => 100,
        'max_limit' => 200,
        // Requests per minute per address on the candidates endpoint.
        'per_minute' => (int) env('KNOWLEDGE_CANDIDATES_PER_MINUTE', 60),
    ],
];
