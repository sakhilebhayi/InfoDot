<?php

namespace App\Services\Points;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * InfoDot's client for the Dot Points service (Dot.Dopemine). Server to server only: every request is signed
 * with HMAC-SHA256 over "{timestamp}.{raw body}" (the same scheme as Notify and Analytics), and nothing a browser
 * sends ever reaches the ledger.
 */
class PointsClient
{
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
        if (! $this->enabled()) {
            return null;
        }

        return Cache::remember('points:summary:'.$userUuid, 60, function () use ($userUuid) {
            try {
                $response = $this->request('')->get(rtrim((string) config('services.dopemine.url'), '/').'/api/points/'.$userUuid);
            } catch (ConnectionException) {
                return null;
            }

            return $response->successful() ? $response->json() : null;
        });
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
        ])->acceptJson()->timeout((int) config('services.dopemine.timeout', 5));
    }
}
