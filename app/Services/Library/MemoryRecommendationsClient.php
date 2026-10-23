<?php

namespace App\Services\Library;

use App\Models\LibraryEntry;
use App\Support\Knowledge\Signature;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * InfoDot's client for Dot.Memory's knowledge recommendations: what Dot.Brain recommends for the Verified library, and
 * the report of what a moderator decided. Server to server, signed (see Signature) with KNOWLEDGE_SECRET_INFODOT.
 *
 * Fail-closed and honest: with no URL or secret every call throws NOT_CONFIGURED before any request is made, and an
 * answer that is not the documented shape is MALFORMED, never repaired or guessed. Items that do not match the contract
 * are left out rather than shown. Logs hold the operation, the status and Memory's short error code, never a body, URL
 * or secret.
 */
class MemoryRecommendationsClient
{
    public const VERDICT = 'recommend_for_library';

    /** Set once a read this instance made could not reach Memory: the rest of that page request then fails fast. Never applies to outcomes, which the outbox paces itself. */
    private bool $unreachable = false;

    public function enabled(): bool
    {
        return filled(config('knowledge.memory_url')) && filled(config('knowledge.infodot_secret'));
    }

    /**
     * One page of Brain's recommendations for the library.
     *
     * @param  'open'|'decided'|'all'  $status
     * @return array{items: list<array<string, mixed>>, next_cursor: string|null, skipped: int}
     *
     * @throws RecommendationsUnavailableException
     */
    public function recommendations(string $status = 'open', int $limit = 50, ?string $cursor = null): array
    {
        $query = array_filter(['status' => $status, 'verdict' => self::VERDICT, 'limit' => max(1, min(200, $limit)), 'cursor' => $cursor], fn ($v) => $v !== null && $v !== '');

        $response = $this->call('recommendations', fn () => $this->request('')->get($this->endpoint('/api/knowledge-recommendations'), $query));

        $rows = $response->json('items');
        $next = $response->json('next_cursor');

        if (! $response->successful() || ! is_array($rows) || ! array_is_list($rows) || ($next !== null && ! is_string($next))) {
            $this->note('recommendations', $response->status(), $response->successful() ? 'malformed' : $this->codeOf($response));
            throw new RecommendationsUnavailableException($response->successful() ? RecommendationsUnavailableException::MALFORMED : RecommendationsUnavailableException::REJECTED);
        }

        $items = [];
        foreach ($rows as $row) {
            if ($item = $this->item($row)) {
                $items[] = $item;
            }
        }

        return ['items' => $items, 'next_cursor' => $next, 'skipped' => count($rows) - count($items)];
    }

    /**
     * Tell Memory what the moderator decided. Succeeds only on a 2xx that is Memory's JSON.
     *
     * @param  'adopted'|'declined'  $outcome
     *
     * @throws OutcomeRefusedException decision_not_found, or a malformed outcome
     * @throws RecommendationsUnavailableException
     */
    public function reportOutcome(string $decisionEventId, string $outcome, string $actorRef): void
    {
        $body = (string) json_encode(['outcome' => $outcome, 'actor_ref' => $actorRef], JSON_UNESCAPED_SLASHES);

        $response = $this->call('outcome', fn () => $this->request($body)->withBody($body, 'application/json')
            ->post($this->endpoint('/api/knowledge-recommendations/'.rawurlencode($decisionEventId).'/outcome')));

        if ($response->successful()) {
            if (is_array($response->json())) {
                return;
            }
            $this->note('outcome', $response->status(), 'malformed');
            throw new RecommendationsUnavailableException(RecommendationsUnavailableException::MALFORMED);
        }

        $code = $this->codeOf($response);
        if ($response->status() === 404 && $code === 'decision_not_found') {
            throw new OutcomeRefusedException(OutcomeRefusedException::NOT_FOUND);
        }
        if ($response->status() === 422 || $response->status() === 404) {
            $this->note('outcome', $response->status(), $code === '' ? 'unknown' : $code);
            throw new OutcomeRefusedException(OutcomeRefusedException::INVALID);
        }

        $this->note('outcome', $response->status(), $code === '' ? 'unknown' : $code);
        throw new RecommendationsUnavailableException($response->status() === 403 ? RecommendationsUnavailableException::REJECTED : RecommendationsUnavailableException::MALFORMED);
    }

    /** One item, only if it is the documented shape and about an answer or a solution; otherwise null. @return array<string, mixed>|null */
    private function item(mixed $row): ?array
    {
        if (! is_array($row)) {
            return null;
        }

        $id = $row['decision_event_id'] ?? null;
        $ref = $row['contribution_ref'] ?? null;

        if (! is_string($id) || $id === '' || strlen($id) > 120 || ! is_string($ref) || LibraryEntry::parseRef($ref) === null || ($row['verdict'] ?? null) !== self::VERDICT) {
            return null;
        }

        $rationale = [];
        foreach (is_array($row['rationale'] ?? null) ? $row['rationale'] : [] as $step) {
            if (is_array($step) && is_string($step['rule'] ?? null)) {
                $rationale[] = [
                    'rule' => $step['rule'],
                    'effect' => is_scalar($step['effect'] ?? null) ? (string) $step['effect'] : '',
                    'detail' => is_scalar($step['detail'] ?? null) ? (string) $step['detail'] : '',
                ];
            }
        }

        $confidence = $row['confidence'] ?? null;

        return [
            'decision_event_id' => $id,
            'contribution_ref' => trim($ref),
            'confidence' => is_int($confidence) || is_float($confidence) || is_string($confidence) ? $confidence : null,
            'rationale' => $rationale,
            'rule_version' => is_scalar($row['rule_version'] ?? null) ? (string) $row['rule_version'] : null,
            'decided_at' => is_string($row['decided_at'] ?? null) ? $row['decided_at'] : null,
            'status' => is_string($row['status'] ?? null) ? $row['status'] : null,
        ];
    }

    /**
     * @param  callable(): Response  $send
     *
     * @throws RecommendationsUnavailableException
     */
    private function call(string $operation, callable $send): Response
    {
        if (! $this->enabled()) {
            throw new RecommendationsUnavailableException(RecommendationsUnavailableException::NOT_CONFIGURED, 'Dot.Memory is not connected yet.');
        }
        if ($this->unreachable && $operation === 'recommendations') {
            throw new RecommendationsUnavailableException(RecommendationsUnavailableException::UNREACHABLE);
        }

        try {
            $response = $send();
        } catch (ConnectionException) {
            $this->unreachable = $operation === 'recommendations';
            $this->note($operation, 0, 'connection');
            throw new RecommendationsUnavailableException(RecommendationsUnavailableException::UNREACHABLE);
        }

        if ($response->status() === 401) {
            // Most likely the shared secret differs from the one Memory holds. Never show or log either.
            $this->note($operation, 401, $this->codeOf($response));
            throw new RecommendationsUnavailableException(RecommendationsUnavailableException::REJECTED);
        }
        if ($response->serverError() || $response->status() === 429) {
            $this->note($operation, $response->status(), $this->codeOf($response));
            throw new RecommendationsUnavailableException(RecommendationsUnavailableException::UNREACHABLE);
        }

        return $response;
    }

    private function codeOf(Response $response): string
    {
        $body = $response->json();

        return is_array($body) && is_string($body['code'] ?? null) ? substr((string) preg_replace('/[^a-z_]/', '', $body['code']), 0, 40) : '';
    }

    private function note(string $operation, int $status, string $code): void
    {
        Log::warning('knowledge.memory_problem', ['operation' => $operation, 'status' => $status, 'code' => $code]);
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('knowledge.memory_url'), '/').$path;
    }

    private function request(string $body): PendingRequest
    {
        return Http::withHeaders(Signature::headers((string) config('knowledge.infodot_secret'), $body))
            ->acceptJson()->connectTimeout(3)->timeout((int) config('knowledge.timeout', 5));
    }
}
