<?php

namespace App\Services\Platforms;

use App\Models\PlatformEntitlement;
use App\Models\Team;
use App\Services\Demo\DemoRegistry;
use App\Support\EcosystemHandoff;
use App\Support\MembershipWebhook;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Durable delivery of entitlement changes to the platform they are about (R-50), in the shape of the points outbox
 * (App\Services\Points\RewardOutbox): a row is written in the same transaction as the change and sent later, so an
 * unreachable platform loses nothing and never delays the screen that caused the change.
 *
 * Delivery is a signed POST to {platform}/api/ecosystem/entitlement: HMAC-SHA256 over "{timestamp}.{body}" with the
 * platform's own secret, the same scheme and headers as the membership webhook. Events are idempotent by `event_id`.
 *
 * Failure handling, as the points outbox: connection errors, 5xx and 429 are retried with growing delays (30 s,
 * 2 min, 10 min, 30 min, 1 h, then every 6 h, 25 attempts at most); 401/403 mean the shared secret is wrong, an
 * operator problem, retried slowly and logged as errors; 409 and 425 are retried like an outage; any other 4xx is a
 * bad event and is parked as failed. Two differences, both about platforms that are not ready: a platform with no
 * secret or no trusted address here is retried slowly like a wrong secret, and a platform that answers 404 has not
 * built the receiver yet, so after three attempts the event is recorded as delivered-not-implemented instead of
 * being retried for days. That loses nothing: every platform also reads `entitled` when a person signs in.
 *
 * Order per company and platform: an event waits while an earlier one about the same pair is still undelivered.
 */
class EntitlementOutbox
{
    public const EVENT = 'entitlement.changed';

    public const NOT_IMPLEMENTED = 'not implemented by the platform (HTTP 404)';

    private const DELAYS = [30, 120, 600, 1800, 3600];

    private const MAX_ATTEMPTS = 25;

    private const NOT_IMPLEMENTED_AFTER = 3;

    /** @param  array<string, mixed>  $details */
    public function enqueue(Team $team, PlatformEntitlement $entitlement, string $type, int $eventId, array $details = []): void
    {
        // A demonstration company (R-49) is fictional: no platform is told about it, and removal has nothing to chase.
        if (app(DemoRegistry::class)->isDemoTeam($team)) {
            return;
        }

        $envelope = [
            'event_id' => 'entitlement-'.$eventId,
            'event' => self::EVENT,
            'type' => $type,
            'platform' => $entitlement->platform_key,
            'team_uuid' => (string) $team->uuid,
            'status' => $entitlement->status,
            'entitled' => $entitlement->grants(),
            'source' => $entitlement->source,
            'access_mode' => $entitlement->access_mode,
            'occurred_at' => now()->toIso8601String(),
        ] + (isset($details['user_uuid']) ? ['user_uuid' => $details['user_uuid'], 'platform_role' => $details['platform_role'] ?? null] : []);

        DB::table('entitlement_outbox')->insertOrIgnore([
            'event_id' => $envelope['event_id'],
            'platform_key' => $entitlement->platform_key,
            'team_uuid' => (string) $team->uuid,
            'payload' => json_encode($envelope, JSON_UNESCAPED_SLASHES),
            'next_attempt_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Sends what is due. Returns how many were delivered. Stops early when platforms are unreachable. */
    public function flush(int $limit = 50): int
    {
        $sent = 0;
        $unreachable = 0;

        $due = DB::table('entitlement_outbox')->whereNull('sent_at')->whereNull('failed_at')->where('next_attempt_at', '<=', now())
            ->orderBy('id')->limit($limit)->get();

        foreach ($due as $row) {
            if ($this->waitsForEarlierEvent($row)) {
                continue;
            }

            // Claim the row before sending, as the points outbox does: overlapping flushes must not both send it, and a
            // process that dies mid-send gives the row back after five minutes (at least once).
            $claimed = DB::table('entitlement_outbox')->where('id', $row->id)->whereNull('sent_at')->whereNull('failed_at')->where('next_attempt_at', '<=', now())
                ->update(['next_attempt_at' => now()->addMinutes(5)]);

            if ($claimed === 0) {
                continue;
            }

            $secret = (string) config('ecosystem.service_secrets.'.$row->platform_key, '');
            $url = EcosystemHandoff::apiUrlFor((string) $row->platform_key, '/api/ecosystem/entitlement');

            if ($secret === '' || $url === null) {
                $this->retryLater($row, 'no service secret or trusted address for this platform', 3600);

                continue;
            }

            $body = (string) $row->payload;
            $timestamp = time();

            try {
                $response = Http::withHeaders([
                    MembershipWebhook::HEADER_TIMESTAMP => (string) $timestamp,
                    MembershipWebhook::HEADER_SIGNATURE => MembershipWebhook::sign($secret, $timestamp, $body),
                ])->acceptJson()->timeout(4)->withBody($body, 'application/json')->post($url);
            } catch (ConnectionException $e) {
                $this->retryLater($row, 'unreachable');
                if (++$unreachable >= 3) {
                    break;
                }

                continue;
            }

            if ($response->successful()) {
                DB::table('entitlement_outbox')->where('id', $row->id)->update(['sent_at' => now(), 'last_error' => null, 'updated_at' => now()]);
                $sent++;
            } elseif ($response->status() === 404) {
                // The platform has no receiver yet. Three tries, then it is written down as delivered-not-implemented.
                if ($row->attempts + 1 >= self::NOT_IMPLEMENTED_AFTER) {
                    DB::table('entitlement_outbox')->where('id', $row->id)->update(['attempts' => $row->attempts + 1, 'sent_at' => now(), 'last_error' => self::NOT_IMPLEMENTED, 'updated_at' => now()]);
                } else {
                    $this->retryLater($row, 'HTTP 404');
                }
            } elseif ($response->serverError() || in_array($response->status(), [409, 425, 429], true)) {
                $this->retryLater($row, 'HTTP '.$response->status());
            } elseif (in_array($response->status(), [401, 403], true)) {
                Log::error('A platform refused our entitlement signature: the shared secret is wrong or missing on one side.', ['platform' => $row->platform_key, 'status' => $response->status()]);
                $this->retryLater($row, 'HTTP '.$response->status().' (check the shared secret)', 3600);
            } else {
                DB::table('entitlement_outbox')->where('id', $row->id)->update(['failed_at' => now(), 'last_error' => 'HTTP '.$response->status(), 'updated_at' => now()]);
                Log::warning('An entitlement event was refused by its platform and parked.', ['platform' => $row->platform_key, 'status' => $response->status()]);
            }
        }

        return $sent;
    }

    /**
     * Put an undelivered event back at the front of the queue: a parked one is un-parked, a waiting one is made due
     * now, and the attempt count starts again. A delivered event is left alone. Returns whether anything changed.
     */
    public function retry(int $id): bool
    {
        return DB::table('entitlement_outbox')->where('id', $id)->whereNull('sent_at')
            ->update(['failed_at' => null, 'attempts' => 0, 'next_attempt_at' => now(), 'updated_at' => now()]) === 1;
    }

    /** True while an earlier event about the same company and platform has neither been delivered nor been parked. */
    private function waitsForEarlierEvent(object $row): bool
    {
        return DB::table('entitlement_outbox')->where('platform_key', $row->platform_key)->where('team_uuid', $row->team_uuid)
            ->where('id', '<', $row->id)->whereNull('sent_at')->whereNull('failed_at')->exists();
    }

    private function retryLater(object $row, string $error, ?int $minimumDelay = null): void
    {
        $attempts = $row->attempts + 1;

        if ($attempts >= self::MAX_ATTEMPTS) {
            DB::table('entitlement_outbox')->where('id', $row->id)->update(['attempts' => $attempts, 'failed_at' => now(), 'last_error' => 'gave up: '.$error, 'updated_at' => now()]);

            return;
        }

        $delay = max($minimumDelay ?? 0, self::DELAYS[$attempts - 1] ?? 21600);

        DB::table('entitlement_outbox')->where('id', $row->id)->update([
            'attempts' => $attempts, 'next_attempt_at' => now()->addSeconds($delay), 'last_error' => $error, 'updated_at' => now(),
        ]);
    }
}
