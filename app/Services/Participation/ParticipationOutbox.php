<?php

namespace App\Services\Participation;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Durable delivery of participation events to Dot.Memory (only for people who opted in).
 *
 * A row is written when the acceptance or verification happens and sent later, so a Memory outage loses nothing.
 * Failure handling matches the points outbox: connection errors, 5xx and 429 are retried with growing delays (30 s,
 * 2 min, 10 min, 30 min, 1 h, then every 6 h, 25 attempts at most); a rejected signature is an operator problem, logged
 * as an error and retried slowly; an event Memory refuses (invalid, or no consent on its side) is parked as failed and
 * never retried; a conflict on a deterministic id means Memory already holds that very fact and counts as delivered.
 *
 * Order per contribution: an event waits while an earlier event about the same contribution of the same person is
 * still undelivered. And before every send the person's switch is checked again: if they turned sharing off since the
 * event was queued, the event is dropped, never sent.
 */
class ParticipationOutbox
{
    private const DELAYS = [30, 120, 600, 1800, 3600];

    private const MAX_ATTEMPTS = 25;

    public function __construct(private readonly ParticipationClient $client) {}

    /**
     * Queue one event. The same event id is queued once, however often the same fact is raised.
     *
     * @param  array{event_id: string, user_uuid: string, occurred_at: string, topic: string, kind: string, contribution_ref: string}  $event
     */
    public function enqueue(User $user, array $event): bool
    {
        return DB::table('participation_outbox')->insertOrIgnore([
            'event_id' => $event['event_id'],
            'user_id' => $user->id,
            'contribution_ref' => $event['contribution_ref'],
            'topic' => $event['topic'],
            'kind' => $event['kind'],
            'payload' => json_encode($event, JSON_UNESCAPED_SLASHES),
            'next_attempt_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]) === 1;
    }

    /** Forget everything queued or sent for a person (they turned sharing off, or are starting afresh). */
    public function forget(User $user): void
    {
        DB::table('participation_outbox')->where('user_id', $user->id)->delete();
    }

    /** How many of this person's events are queued but not yet in Memory. */
    public function waitingFor(User $user): int
    {
        return DB::table('participation_outbox')->where('user_id', $user->id)->whereNull('sent_at')->whereNull('failed_at')->count();
    }

    /** Sends what is due. Returns how many were delivered. Stops early when Memory is unreachable. */
    public function flush(int $limit = 50): int
    {
        if (! $this->client->enabled()) {
            return 0;
        }

        $sent = 0;
        $unreachable = 0;

        $due = DB::table('participation_outbox')->whereNull('sent_at')->whereNull('failed_at')->where('next_attempt_at', '<=', now())
            ->orderBy('id')->limit($limit)->get();

        foreach ($due as $row) {
            if ($this->waitsForEarlierEvent($row)) {
                continue;
            }

            if (! User::whereKey($row->user_id)->value('share_expertise')) {
                DB::table('participation_outbox')->where('id', $row->id)->delete(); // They turned it off: nothing is sent.

                continue;
            }

            try {
                $this->client->sendEvent(json_decode($row->payload, true));
            } catch (ParticipationRefusedException $e) {
                if ($e->reason === ParticipationRefusedException::CONFLICT) {
                    $this->markSent($row); // Memory already holds this very event.
                    $sent++;
                } else {
                    DB::table('participation_outbox')->where('id', $row->id)->update(['failed_at' => now(), 'last_error' => $e->reason, 'updated_at' => now()]);
                    Log::warning('participation.event_refused', ['reason' => $e->reason, 'kind' => $row->kind]);
                }

                continue;
            } catch (ParticipationUnavailableException $e) {
                if ($e->reason === ParticipationUnavailableException::NOT_CONFIGURED) {
                    break;
                }
                if ($e->reason === ParticipationUnavailableException::REJECTED) {
                    Log::error('Dot.Memory refused our signature: the participation secret is wrong or missing on one side.');
                    $this->retryLater($row, 'rejected (check the shared secret)', 3600);
                } else {
                    $this->retryLater($row, $e->reason);
                }
                if ($e->reason === ParticipationUnavailableException::UNREACHABLE && ++$unreachable >= 3) {
                    break;
                }

                continue;
            }

            $this->markSent($row);
            $sent++;
        }

        return $sent;
    }

    /** Delivered rows are only kept as long as they are useful for not queueing the same fact again. */
    public function prune(int $keepSentDays = 30): int
    {
        return DB::table('participation_outbox')->whereNotNull('sent_at')->where('sent_at', '<', now()->subDays($keepSentDays))->delete();
    }

    private function markSent(object $row): void
    {
        DB::table('participation_outbox')->where('id', $row->id)->update(['sent_at' => now(), 'last_error' => null, 'updated_at' => now()]);
    }

    /** True while an earlier event about the same contribution of the same person has neither been delivered nor been parked. */
    private function waitsForEarlierEvent(object $row): bool
    {
        return DB::table('participation_outbox')->where('id', '<', $row->id)
            ->where('user_id', $row->user_id)->where('contribution_ref', $row->contribution_ref)
            ->whereNull('sent_at')->whereNull('failed_at')->exists();
    }

    private function retryLater(object $row, string $error, ?int $minimumDelay = null): void
    {
        $attempts = $row->attempts + 1;

        if ($attempts >= self::MAX_ATTEMPTS) {
            DB::table('participation_outbox')->where('id', $row->id)->update(['attempts' => $attempts, 'failed_at' => now(), 'last_error' => 'gave up: '.$error, 'updated_at' => now()]);

            return;
        }

        $delay = max($minimumDelay ?? 0, self::DELAYS[$attempts - 1] ?? 21600);

        DB::table('participation_outbox')->where('id', $row->id)->update([
            'attempts' => $attempts, 'next_attempt_at' => now()->addSeconds($delay), 'last_error' => substr($error, 0, 255), 'updated_at' => now(),
        ]);
    }
}
