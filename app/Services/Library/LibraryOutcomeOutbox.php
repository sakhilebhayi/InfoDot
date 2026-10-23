<?php

namespace App\Services\Library;

use App\Models\LibraryOutcome;
use Illuminate\Support\Facades\Log;

/**
 * Durable delivery of moderator decisions to Dot.Memory. The row in `library_outcomes` is written in the same transaction
 * as the decision and sent later, so a Memory outage loses nothing.
 *
 * Failure handling matches the other outboxes: connection errors, 5xx and 429 are retried with growing delays (30 s,
 * 2 min, 10 min, 30 min, 1 h, then every 6 h, 25 attempts at most); a rejected signature is an operator problem, logged
 * as an error and retried slowly; Memory saying it does not know the decision (or that the outcome is malformed) is
 * final: the row is parked as failed, never retried, and the moderator's decision in InfoDot stands.
 */
class LibraryOutcomeOutbox
{
    private const DELAYS = [30, 120, 600, 1800, 3600];

    private const MAX_ATTEMPTS = 25;

    public function __construct(private readonly MemoryRecommendationsClient $client) {}

    /** Sends what is due. Returns how many were delivered. Stops early when Memory is unreachable or not configured. */
    public function flush(int $limit = 50): int
    {
        if (! $this->client->enabled()) {
            return 0;
        }

        $sent = 0;
        $unreachable = 0;

        $due = LibraryOutcome::whereNull('sent_at')->whereNull('failed_at')->where('next_attempt_at', '<=', now())->orderBy('id')->limit($limit)->get();

        foreach ($due as $row) {
            try {
                $this->client->reportOutcome($row->decision_event_id, $row->outcome, $row->actor_ref);
            } catch (OutcomeRefusedException $e) {
                $row->forceFill(['failed_at' => now(), 'last_error' => $e->reason])->save();
                Log::warning('knowledge.outcome_refused', ['reason' => $e->reason, 'outcome' => $row->outcome]);

                continue;
            } catch (RecommendationsUnavailableException $e) {
                if ($e->reason === RecommendationsUnavailableException::NOT_CONFIGURED) {
                    break;
                }
                if ($e->reason === RecommendationsUnavailableException::REJECTED) {
                    Log::error('Dot.Memory refused our signature: the knowledge secret is wrong or missing on one side.');
                    $this->retryLater($row, 'rejected (check the shared secret)', 3600);
                } else {
                    $this->retryLater($row, $e->reason);
                }
                if ($e->reason === RecommendationsUnavailableException::UNREACHABLE && ++$unreachable >= 3) {
                    break;
                }

                continue;
            }

            $row->forceFill(['sent_at' => now(), 'last_error' => null])->save();
            $sent++;
        }

        return $sent;
    }

    /** How many decisions Memory has not been told about yet (still being retried). */
    public function waiting(): int
    {
        return LibraryOutcome::whereNull('sent_at')->whereNull('failed_at')->count();
    }

    private function retryLater(LibraryOutcome $row, string $error, ?int $minimumDelay = null): void
    {
        $attempts = $row->attempts + 1;

        if ($attempts >= self::MAX_ATTEMPTS) {
            $row->forceFill(['attempts' => $attempts, 'failed_at' => now(), 'last_error' => substr('gave up: '.$error, 0, 255)])->save();

            return;
        }

        $delay = max($minimumDelay ?? 0, self::DELAYS[$attempts - 1] ?? 21600);

        $row->forceFill(['attempts' => $attempts, 'next_attempt_at' => now()->addSeconds($delay), 'last_error' => substr($error, 0, 255)])->save();
    }
}
