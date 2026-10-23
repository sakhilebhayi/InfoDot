<?php

namespace Tests\Feature\Participation;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * A stand-in for the Dot.Memory participation API (never the real service). It behaves like the documented contract:
 * it CHECKS the HMAC signature (401 when it does not match), refuses events without consent (403), holds an event id
 * once, validates the closed field list (422), purges on revoke and erases on DELETE. Tests can force any answer with
 * $this->memoryOverride["METHOD /path"] and read every request the app sent from $this->memoryRequests.
 */
trait FakesMemory
{
    protected const MEMORY_SECRET = 'test-only-participation-secret-81ac';

    /** @var array<string, bool> user_uuid => consent granted */
    protected array $memoryConsent = [];

    /** @var array<string, array<string, mixed>> event_id => event */
    protected array $memoryEvents = [];

    /** @var array<string, mixed> "METHOD /path" (with {uuid}) => Http::response(...) | callable */
    protected array $memoryOverride = [];

    /** @var list<Request> */
    protected array $memoryRequests = [];

    protected function fakeMemory(bool $enabled = true): void
    {
        config([
            'participation.enabled' => $enabled,
            'participation.url' => 'https://memory.test',
            'participation.secret' => self::MEMORY_SECRET,
        ]);

        Http::fake(function (Request $request) {
            $this->memoryRequests[] = $request;
            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            $key = $request->method().' '.preg_replace('#^/api/participation/[A-Za-z0-9-]{8,64}(/|$)#', '/api/participation/{uuid}$1', $path);

            if (isset($this->memoryOverride[$key])) {
                $answer = $this->memoryOverride[$key];

                return is_callable($answer) ? $answer($request) : $answer;
            }

            $timestamp = $request->header('X-InfoDot-Timestamp')[0] ?? '';
            $signature = $request->header('X-InfoDot-Signature')[0] ?? '';
            if ($signature !== 'sha256='.hash_hmac('sha256', $timestamp.'.'.$request->body(), self::MEMORY_SECRET) || abs(time() - (int) $timestamp) > 300) {
                return Http::response(['code' => 'invalid_signature', 'message' => 'Invalid signature.'], 401);
            }

            $uuid = preg_match('#^/api/participation/([A-Za-z0-9-]{8,64})(/|$)#', $path, $m) ? $m[1] : null;
            $body = json_decode($request->body(), true);

            return match ($key) {
                'PUT /api/participation/{uuid}/consent' => $this->memorySetConsent($uuid, $body),
                'POST /api/participation/events' => $this->memoryStoreEvent($body),
                'GET /api/participation/{uuid}/context' => $this->memoryContext($uuid),
                'DELETE /api/participation/{uuid}' => $this->memoryErase($uuid),
                default => Http::response(['code' => 'not_found', 'message' => 'x'], 404),
            };
        });
    }

    private function memoryConsentBody(string $uuid): array
    {
        return ['granted' => $this->memoryConsent[$uuid] ?? false, 'granted_at' => '2026-10-23T08:00:00Z', 'revoked_at' => null, 'source' => 'infodot'];
    }

    private function memorySetConsent(?string $uuid, mixed $body)
    {
        if (! is_array($body) || array_keys($body) !== ['granted'] || ! is_bool($body['granted'])) {
            return Http::response(['code' => 'invalid_request', 'message' => 'x'], 422);
        }
        $purged = 0;
        if (! $body['granted']) {
            foreach ($this->memoryEvents as $id => $event) {
                if ($event['user_uuid'] === $uuid) {
                    unset($this->memoryEvents[$id]);
                    $purged++;
                }
            }
        }
        $this->memoryConsent[$uuid] = $body['granted'];

        return Http::response(['data' => ['user_uuid' => $uuid, 'consent' => $this->memoryConsentBody($uuid), 'purged' => $purged]]);
    }

    private function memoryStoreEvent(mixed $body)
    {
        $allowed = ['event_id', 'user_uuid', 'occurred_at', 'topic', 'kind', 'contribution_ref'];
        if (! is_array($body) || array_diff(array_keys($body), $allowed) !== [] || array_diff($allowed, array_keys($body)) !== []) {
            return Http::response(['code' => 'invalid_request', 'message' => 'x', 'errors' => []], 422);
        }
        if (! ($this->memoryConsent[$body['user_uuid']] ?? false)) {
            return Http::response(['code' => 'consent_required', 'message' => 'x'], 403);
        }
        if (isset($this->memoryEvents[$body['event_id']])) {
            return Http::response(['data' => $body], 200);
        }
        $this->memoryEvents[$body['event_id']] = $body;

        return Http::response(['data' => $body], 201);
    }

    private function memoryContext(?string $uuid)
    {
        if (! ($this->memoryConsent[$uuid] ?? false)) {
            return Http::response(['code' => 'consent_required', 'message' => 'x'], 403);
        }
        $topics = [];
        foreach ($this->memoryEvents as $event) {
            if ($event['user_uuid'] === $uuid) {
                $topics[$event['topic']]['count'] = ($topics[$event['topic']]['count'] ?? 0) + 1;
                $topics[$event['topic']]['kinds'][$event['kind']] = ($topics[$event['topic']]['kinds'][$event['kind']] ?? 0) + 1;
            }
        }
        $rows = [];
        foreach ($topics as $topic => $t) {
            $rows[] = ['topic' => $topic, 'count' => $t['count'], 'kinds' => $t['kinds']];
        }

        return Http::response(['data' => ['user_uuid' => $uuid, 'consent' => $this->memoryConsentBody($uuid), 'topics' => $rows]]);
    }

    private function memoryErase(?string $uuid)
    {
        $deleted = 0;
        foreach ($this->memoryEvents as $id => $event) {
            if ($event['user_uuid'] === $uuid) {
                unset($this->memoryEvents[$id]);
                $deleted++;
            }
        }
        $had = array_key_exists($uuid, $this->memoryConsent);
        unset($this->memoryConsent[$uuid]);

        return Http::response(['data' => ['user_uuid' => $uuid, 'records_deleted' => $deleted, 'consent_deleted' => $had]]);
    }

    /** @return list<Request> */
    protected function memorySent(string $method, ?string $pathPattern = null): array
    {
        return array_values(array_filter($this->memoryRequests, fn (Request $r) => $r->method() === $method
            && ($pathPattern === null || preg_match($pathPattern, (string) parse_url($r->url(), PHP_URL_PATH)) === 1)));
    }
}
