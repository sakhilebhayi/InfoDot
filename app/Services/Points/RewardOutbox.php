<?php

namespace App\Services\Points;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Durable delivery of contribution events to the points ledger.
 *
 * A row is written when the event happens and sent later, so an outage of the ledger or of the queue loses nothing.
 * Failure handling: connection errors, 5xx and 429 are retried with growing delays (30 s, 2 min, 10 min, 30 min,
 * 1 h, then every 6 h, 25 attempts at most); 401/403 mean the shared secret is wrong, an operator problem, and are
 * retried slowly and logged as errors; any other 4xx is a bad event and is parked as failed, never retried.
 */
class RewardOutbox
{
    private const DELAYS = [30, 120, 600, 1800, 3600];

    private const MAX_ATTEMPTS = 25;

    public function __construct(private readonly PointsClient $client) {}

    /** @param array<string, mixed> $envelope */
    public function enqueue(array $envelope): void
    {
        DB::table('reward_outbox')->insertOrIgnore([
            'event_id' => $envelope['event_id'],
            'type' => $envelope['type'],
            'payload' => json_encode($envelope, JSON_UNESCAPED_SLASHES),
            'next_attempt_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Sends what is due. Returns how many were delivered. Stops early when the service is unreachable. */
    public function flush(int $limit = 50): int
    {
        if (! $this->client->enabled()) {
            return 0;
        }

        $sent = 0;
        $unreachable = 0;

        $due = DB::table('reward_outbox')->whereNull('sent_at')->whereNull('failed_at')->where('next_attempt_at', '<=', now())
            ->orderBy('id')->limit($limit)->get();

        foreach ($due as $row) {
            try {
                $response = $this->client->send(json_decode($row->payload, true));
            } catch (ConnectionException $e) {
                $this->retryLater($row, 'unreachable');
                if (++$unreachable >= 3) {
                    break;
                }

                continue;
            }

            if ($response->successful()) {
                DB::table('reward_outbox')->where('id', $row->id)->update(['sent_at' => now(), 'last_error' => null, 'updated_at' => now()]);
                $sent++;
            } elseif ($response->serverError() || $response->status() === 429) {
                $this->retryLater($row, 'HTTP '.$response->status());
            } elseif (in_array($response->status(), [401, 403], true)) {
                Log::error('Points ledger refused our signature: the shared secret is wrong or missing on one side.', ['status' => $response->status()]);
                $this->retryLater($row, 'HTTP '.$response->status().' (check the shared secret)', 3600);
            } else {
                DB::table('reward_outbox')->where('id', $row->id)->update(['failed_at' => now(), 'last_error' => 'HTTP '.$response->status(), 'updated_at' => now()]);
                Log::warning('A contribution event was refused by the points ledger and parked.', ['status' => $response->status(), 'type' => $row->type]);
            }
        }

        return $sent;
    }

    private function retryLater(object $row, string $error, ?int $minimumDelay = null): void
    {
        $attempts = $row->attempts + 1;

        if ($attempts >= self::MAX_ATTEMPTS) {
            DB::table('reward_outbox')->where('id', $row->id)->update(['attempts' => $attempts, 'failed_at' => now(), 'last_error' => 'gave up: '.$error, 'updated_at' => now()]);

            return;
        }

        $delay = max($minimumDelay ?? 0, self::DELAYS[$attempts - 1] ?? 21600);

        DB::table('reward_outbox')->where('id', $row->id)->update([
            'attempts' => $attempts, 'next_attempt_at' => now()->addSeconds($delay), 'last_error' => $error, 'updated_at' => now(),
        ]);
    }
}
