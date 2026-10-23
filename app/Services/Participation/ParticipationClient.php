<?php

namespace App\Services\Participation;

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * InfoDot's client for the Dot.Memory participation API. Server to server only; every request is signed with
 * HMAC-SHA256 over "{timestamp}.{raw body}" (a GET or DELETE signs the empty string), the same scheme as the points
 * calls. It names the person only by their InfoDot uuid and sends topic slugs, counts and opaque references: never a
 * name, an address, a title or any content.
 *
 * Fail-closed: unless sharing is switched on AND a URL and secret are set, every call throws NOT_CONFIGURED before
 * any request is made. Logs hold the operation, the status and Memory's short error code, never a body, URL, uuid or secret.
 */
class ParticipationClient
{
    /** Set once a request this instance made could not reach Memory: the rest of the request then fails fast. */
    private bool $unreachable = false;

    public function enabled(): bool
    {
        return config('participation.enabled') === true && filled(config('participation.url')) && filled(config('participation.secret'));
    }

    /**
     * Record that the person says yes (true) or no (false). Succeeds only when Memory confirms the decision it was
     * given, so a caller can say "recorded" without guessing. Revoking also purges what Memory held, on its side.
     *
     * @throws ParticipationUnavailableException
     */
    public function setConsent(User $user, bool $granted): void
    {
        $uuid = $this->uuidOf($user);
        $body = (string) json_encode(['granted' => $granted], JSON_UNESCAPED_SLASHES);

        $response = $this->call('consent', fn () => $this->request($body)->withBody($body, 'application/json')
            ->put($this->endpoint('/api/participation/'.rawurlencode($uuid).'/consent')));

        $consent = $response->json('data.consent');
        if ($response->successful() && is_array($consent) && ($consent['granted'] ?? null) === $granted) {
            return;
        }

        $this->note('consent', $response->status(), $response->successful() ? 'malformed' : $this->codeOf($response));
        throw new ParticipationUnavailableException($response->successful() ? ParticipationUnavailableException::MALFORMED : ParticipationUnavailableException::REJECTED);
    }

    /**
     * Send one participation event. Memory counts an event_id once however often it is sent.
     *
     * @param  array{event_id: string, user_uuid: string, occurred_at: string, topic: string, kind: string, contribution_ref: string}  $event
     *
     * @throws ParticipationRefusedException consent_required (Memory holds no consent), invalid_request, or event_conflict
     * @throws ParticipationUnavailableException
     */
    public function sendEvent(array $event): void
    {
        $body = (string) json_encode([
            'event_id' => $event['event_id'], 'user_uuid' => $event['user_uuid'], 'occurred_at' => $event['occurred_at'],
            'topic' => $event['topic'], 'kind' => $event['kind'], 'contribution_ref' => $event['contribution_ref'],
        ], JSON_UNESCAPED_SLASHES);

        $response = $this->call('event', fn () => $this->request($body)->withBody($body, 'application/json')->post($this->endpoint('/api/participation/events')));

        if (in_array($response->status(), [200, 201], true)) {
            if (is_array($response->json())) {
                return;
            }
            // A 2xx that is not Memory's JSON (a proxy's page, say) is not a recording.
            $this->note('event', $response->status(), 'malformed');
            throw new ParticipationUnavailableException(ParticipationUnavailableException::MALFORMED);
        }

        $code = $this->codeOf($response);
        if ($response->status() === 403 && $code === 'consent_required') {
            throw new ParticipationRefusedException(ParticipationRefusedException::CONSENT_REQUIRED);
        }
        if ($response->status() === 409 && $code === 'event_conflict') {
            throw new ParticipationRefusedException(ParticipationRefusedException::CONFLICT);
        }
        if ($response->status() === 422) {
            $this->note('event', 422, $code === '' ? 'unknown' : $code);
            throw new ParticipationRefusedException(ParticipationRefusedException::INVALID);
        }

        $this->note('event', $response->status(), $code === '' ? 'unknown' : $code);
        throw new ParticipationUnavailableException($response->status() === 403 ? ParticipationUnavailableException::REJECTED : ParticipationUnavailableException::MALFORMED);
    }

    /**
     * What Memory holds about the person: topic and counts. Refused with consent_required when Memory has no grant for them.
     *
     * @throws ParticipationRefusedException
     * @throws ParticipationUnavailableException
     */
    public function context(User $user): ParticipationContext
    {
        $uuid = $this->uuidOf($user);
        $response = $this->call('context', fn () => $this->request('')->get($this->endpoint('/api/participation/'.rawurlencode($uuid).'/context')));

        if ($response->status() === 403 && $this->codeOf($response) === 'consent_required') {
            throw new ParticipationRefusedException(ParticipationRefusedException::CONSENT_REQUIRED);
        }

        $data = $response->json('data');
        if ($response->successful() && is_array($data) && is_array($data['topics'] ?? null) && array_is_list($data['topics']) && is_bool($data['consent']['granted'] ?? null)) {
            $topics = [];
            foreach ($data['topics'] as $row) {
                if (! is_array($row) || ! is_string($row['topic'] ?? null) || ! preg_match('/\A[a-z0-9]+(-[a-z0-9]+)*\z/', $row['topic']) || ! is_int($row['count'] ?? null) || $row['count'] < 0) {
                    continue; // Not the documented shape: leave it out rather than show something invented.
                }
                $kinds = [];
                foreach ((array) ($row['kinds'] ?? []) as $kind => $n) {
                    if (in_array($kind, ['accepted', 'verified'], true) && is_int($n) && $n >= 0) {
                        $kinds[$kind] = $n;
                    }
                }
                $topics[] = ['topic' => $row['topic'], 'count' => $row['count'], 'kinds' => $kinds];
            }

            return new ParticipationContext($data['consent']['granted'], $topics);
        }

        $this->note('context', $response->status(), $response->successful() ? 'malformed' : $this->codeOf($response));
        throw new ParticipationUnavailableException($response->successful() ? ParticipationUnavailableException::MALFORMED : ParticipationUnavailableException::REJECTED);
    }

    /**
     * Hard-erase everything Memory holds about the person, including the record of their consent. Idempotent.
     *
     * @throws ParticipationUnavailableException
     */
    public function erase(User $user): void
    {
        $uuid = $this->uuidOf($user);
        $response = $this->call('erase', fn () => $this->request('')->delete($this->endpoint('/api/participation/'.rawurlencode($uuid))));

        if ($response->successful() && is_array($response->json('data'))) {
            return;
        }

        $this->note('erase', $response->status(), $response->successful() ? 'malformed' : $this->codeOf($response));
        throw new ParticipationUnavailableException($response->successful() ? ParticipationUnavailableException::MALFORMED : ParticipationUnavailableException::REJECTED);
    }

    /**
     * Send one signed request. Not configured, unreachable, a bad signature and server errors all become
     * ParticipationUnavailableException; any other status is returned for the caller to read.
     *
     * @param  callable(): Response  $send
     *
     * @throws ParticipationUnavailableException
     */
    private function call(string $operation, callable $send): Response
    {
        if (! $this->enabled()) {
            throw new ParticipationUnavailableException(ParticipationUnavailableException::NOT_CONFIGURED, 'Dot.Memory is not connected yet.');
        }
        if ($this->unreachable) {
            throw new ParticipationUnavailableException(ParticipationUnavailableException::UNREACHABLE);
        }

        try {
            $response = $send();
        } catch (ConnectionException) {
            $this->unreachable = true;
            $this->note($operation, 0, 'connection');
            throw new ParticipationUnavailableException(ParticipationUnavailableException::UNREACHABLE);
        }

        if ($response->status() === 401) {
            // Most likely the shared secret differs from the one Memory holds. Never show or log either.
            $this->note($operation, 401, $this->codeOf($response));
            throw new ParticipationUnavailableException(ParticipationUnavailableException::REJECTED);
        }
        if ($response->serverError() || $response->status() === 429) {
            $this->note($operation, $response->status(), $this->codeOf($response));
            throw new ParticipationUnavailableException(ParticipationUnavailableException::UNREACHABLE);
        }

        return $response;
    }

    /** Memory's `code`, reduced to a short safe token so it can be logged. */
    private function codeOf(Response $response): string
    {
        $body = $response->json();

        return is_array($body) && is_string($body['code'] ?? null) ? substr((string) preg_replace('/[^a-z_]/', '', $body['code']), 0, 40) : '';
    }

    /** What we log about a failed call: the operation, the status and the code. Never a body, a URL, a uuid or a secret. */
    private function note(string $operation, int $status, string $code): void
    {
        Log::warning('participation.upstream_problem', ['operation' => $operation, 'status' => $status, 'code' => $code]);
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('participation.url'), '/').$path;
    }

    /** The person's own uuid: the only identity these calls ever name. */
    private function uuidOf(User $user): string
    {
        $uuid = (string) $user->uuid;
        if (! preg_match('/\A[A-Za-z0-9-]{1,64}\z/', $uuid)) {
            throw new InvalidArgumentException('A person without a valid uuid cannot take part.');
        }

        return $uuid;
    }

    private function request(string $body)
    {
        $timestamp = time();

        return Http::withHeaders([
            'X-InfoDot-Timestamp' => (string) $timestamp,
            'X-InfoDot-Signature' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, (string) config('participation.secret')),
        ])->acceptJson()->connectTimeout(3)->timeout((int) config('participation.timeout', 5));
    }
}
