<?php

namespace App\Services\Points;

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * InfoDot's client for the Dot Points service (Dot.Dopemine). Server to server only: every request is signed
 * with HMAC-SHA256 over "{timestamp}.{raw body}" (the same scheme as Notify and Analytics), and nothing a browser
 * sends ever reaches the ledger.
 */
class PointsClient
{
    /** Set once a request this instance made could not reach the service: the rest of the request then fails fast. */
    private bool $unreachable = false;

    public function enabled(): bool
    {
        return filled(config('services.dopemine.url')) && filled(config('services.dopemine.secret'));
    }

    /** @param array<string, mixed> $envelope */
    public function send(array $envelope): Response
    {
        $body = (string) json_encode($envelope, JSON_UNESCAPED_SLASHES);

        return $this->request($body)->withBody($body, 'application/json')
            ->post(rtrim((string) config('services.dopemine.url'), '/').'/api/ingest/contributions');
    }

    /**
     * What a person's profile shows, or null when the ledger cannot be reached or is not configured (the profile then
     * says so instead of showing zero). Cached for a minute so a profile view never waits on a slow service twice.
     *
     * @return array<string, mixed>|null
     */
    public function summary(string $userUuid): ?array
    {
        if (! $this->enabled() || $this->unreachable) {
            return null;
        }

        return Cache::remember('points:summary:'.$userUuid, 60, function () use ($userUuid) {
            try {
                $response = $this->request('')->get(rtrim((string) config('services.dopemine.url'), '/').'/api/points/'.$userUuid);
            } catch (ConnectionException) {
                $this->unreachable = true;

                return null;
            }

            return $response->successful() ? $response->json() : null;
        });
    }

    /**
     * The reward catalogue (active, redeemable kinds only; the service already hides retired and recognition items).
     * An empty list is a real answer ("nothing to redeem yet"); an outage throws instead of looking like an empty list.
     *
     * @return list<Reward>
     *
     * @throws PointsUnavailableException
     */
    public function rewards(): array
    {
        $response = $this->call('rewards', fn () => $this->request('')->get($this->endpoint('/api/points/rewards')));
        $rows = $this->expectList($response, 'rewards');

        $rewards = [];
        foreach ($rows as $row) {
            if (! is_array($row) || ! is_int($row['id'] ?? null) || ! is_string($row['name'] ?? null)
                || ! in_array($row['kind'] ?? null, Reward::KINDS, true) || ! is_int($row['cost_points'] ?? null)) {
                continue; // Not the documented shape: skip it rather than show something invented.
            }
            $rewards[] = new Reward(
                $row['id'],
                $row['name'],
                $row['kind'],
                isset($row['description']) && is_string($row['description']) && $row['description'] !== '' ? $row['description'] : null,
                max(0, $row['cost_points']),
                ($row['available'] ?? false) === true,
            );
        }

        return $rewards;
    }

    /**
     * Spend points on a reward on behalf of $user. The uuid is always the user's own; nothing a browser sent can
     * change whose points move. $idempotencyKey makes a repeat of the same click safe: the service replays the first
     * answer (200) instead of spending again.
     *
     * @throws RedemptionRefusedException the service said no; nothing was spent
     * @throws PointsUnavailableException the outcome is unknown (retry with the SAME key)
     */
    public function redeem(User $user, int $rewardId, string $idempotencyKey): RedemptionReceipt
    {
        if (! preg_match('/\A[A-Za-z0-9._:-]{8,64}\z/', $idempotencyKey)) {
            throw new InvalidArgumentException('The idempotency key must be 8 to 64 characters of A-Za-z0-9._:-.');
        }
        $uuid = $this->uuidOf($user);
        $body = (string) json_encode(['user_uuid' => $uuid, 'reward_id' => $rewardId, 'idempotency_key' => $idempotencyKey], JSON_UNESCAPED_SLASHES);

        $response = $this->call('redeem', fn () => $this->request($body)->withBody($body, 'application/json')->post($this->endpoint('/api/points/redemptions')));

        if (in_array($response->status(), [200, 201], true)) {
            $r = $response->json();
            if (is_array($r) && is_string($r['redemption_id'] ?? null) && is_string($r['status'] ?? null)
                && is_array($r['reward'] ?? null) && is_int($r['reward']['id'] ?? null) && is_string($r['reward']['name'] ?? null) && is_string($r['reward']['kind'] ?? null)
                && is_int($r['points_spent'] ?? null) && is_int($r['balance_after'] ?? null)) {
                $this->forget((string) $uuid); // The balance and history just changed.

                return new RedemptionReceipt($r['redemption_id'], $r['status'], $r['reward']['id'], $r['reward']['name'], $r['reward']['kind'], $r['points_spent'], $r['balance_after'], $response->status() === 200);
            }

            // Accepted but not in the documented shape: we cannot say what happened, so say so.
            $this->note('redeem', $response->status(), 'malformed');
            throw new PointsUnavailableException(PointsUnavailableException::MALFORMED);
        }

        $this->refuseOrFail('redeem', $response);
    }

    /**
     * The person's own redemptions, newest first.
     *
     * @return list<RedemptionRecord>
     *
     * @throws PointsUnavailableException
     */
    public function redemptions(User $user): array
    {
        $uuid = $this->uuidOf($user);
        $response = $this->call('redemptions', fn () => $this->request('')->get($this->endpoint('/api/points/redemptions'), ['user_uuid' => $uuid]));

        $records = [];
        foreach ($this->expectList($response, 'redemptions') as $r) {
            try {
                if (! is_array($r) || ! is_string($r['redemption_id'] ?? null) || ! is_array($r['reward'] ?? null)
                    || ! is_string($r['reward']['name'] ?? null) || ! is_int($r['points_spent'] ?? null)
                    || ! in_array($r['status'] ?? null, RedemptionRecord::STATUSES, true) || ! is_string($r['requested_at'] ?? null)) {
                    continue;
                }
                $records[] = new RedemptionRecord(
                    $r['redemption_id'],
                    $r['reward']['name'],
                    is_string($r['reward']['kind'] ?? null) ? $r['reward']['kind'] : '',
                    $r['points_spent'],
                    $r['status'],
                    Carbon::parse($r['requested_at']),
                    is_string($r['resolved_at'] ?? null) ? Carbon::parse($r['resolved_at']) : null,
                    ($r['points_returned'] ?? false) === true,
                    is_string($r['message'] ?? null) && $r['message'] !== '' ? $r['message'] : null,
                );
            } catch (Throwable) {
                continue; // An unreadable date is a malformed row, not a reason to lose the others.
            }
        }

        return $records;
    }

    /**
     * Send one signed request. Not configured, unreachable, a bad signature and server errors all become
     * PointsUnavailableException; any other status is returned for the caller to read.
     *
     * @param  callable(): Response  $send
     *
     * @throws PointsUnavailableException
     */
    private function call(string $operation, callable $send): Response
    {
        if (! $this->enabled()) {
            throw new PointsUnavailableException(PointsUnavailableException::NOT_CONFIGURED, 'Points are not connected yet.');
        }
        if ($this->unreachable) {
            throw new PointsUnavailableException(PointsUnavailableException::UNREACHABLE);
        }

        try {
            $response = $send();
        } catch (ConnectionException) {
            $this->unreachable = true;
            $this->note($operation, 0, 'connection');
            throw new PointsUnavailableException(PointsUnavailableException::UNREACHABLE);
        }

        if ($response->status() === 401) {
            // Most likely the shared secret differs from the one Dopemine holds. Never show or log either.
            $this->note($operation, 401, $this->codeOf($response));
            throw new PointsUnavailableException(PointsUnavailableException::REJECTED);
        }
        if ($response->serverError()) {
            $this->note($operation, $response->status(), $this->codeOf($response));
            throw new PointsUnavailableException(PointsUnavailableException::UNREACHABLE);
        }

        return $response;
    }

    /**
     * @return array<int, mixed>
     *
     * @throws PointsUnavailableException
     */
    private function expectList(Response $response, string $operation): array
    {
        $json = $response->json();
        if (! $response->successful() || ! is_array($json) || ! array_is_list($json)) {
            $this->note($operation, $response->status(), 'malformed');
            throw new PointsUnavailableException(PointsUnavailableException::MALFORMED);
        }

        return $json;
    }

    /** @throws RedemptionRefusedException|PointsUnavailableException */
    private function refuseOrFail(string $operation, Response $response): never
    {
        $code = $this->codeOf($response);
        $body = $response->json();

        if (in_array($code, RedemptionRefusedException::CODES, true)) {
            $header = $response->header('Retry-After');

            throw RedemptionRefusedException::fromCode($code, is_array($body) ? $body : [], ctype_digit($header) ? (int) $header : null);
        }

        $this->note($operation, $response->status(), $code === '' ? 'unknown' : $code);
        throw new PointsUnavailableException(PointsUnavailableException::MALFORMED);
    }

    /** The service's `code`, reduced to a short safe token so it can be logged. */
    private function codeOf(Response $response): string
    {
        $body = $response->json();

        return is_array($body) && is_string($body['code'] ?? null) ? substr((string) preg_replace('/[^a-z_]/', '', $body['code']), 0, 40) : '';
    }

    /** What we log about a failed call: the operation, the status and the code. Never a body, a URL, a uuid or a secret. */
    private function note(string $operation, int $status, string $code): void
    {
        Log::warning('points.upstream_problem', ['operation' => $operation, 'status' => $status, 'code' => $code]);
    }

    private function endpoint(string $path): string
    {
        return rtrim((string) config('services.dopemine.url'), '/').$path;
    }

    /** The authenticated person's own uuid: the only identity these calls ever name. */
    private function uuidOf(User $user): string
    {
        $uuid = (string) $user->uuid;
        if ($uuid === '') {
            throw new PointsUnavailableException(PointsUnavailableException::MALFORMED);
        }

        return $uuid;
    }

    public function forget(string $userUuid): void
    {
        Cache::forget('points:summary:'.$userUuid);
    }

    private function request(string $body)
    {
        $timestamp = time();

        return Http::withHeaders([
            'X-InfoDot-Timestamp' => (string) $timestamp,
            'X-InfoDot-Signature' => 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, (string) config('services.dopemine.secret')),
        ])->acceptJson()->connectTimeout(3)->timeout((int) config('services.dopemine.timeout', 5));
    }
}
