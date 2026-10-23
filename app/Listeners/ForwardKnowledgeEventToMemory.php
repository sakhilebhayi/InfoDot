<?php

namespace App\Listeners;

use App\Events\Knowledge\AnswerAccepted;
use App\Events\Knowledge\SolutionVerified;
use App\Models\User;
use App\Services\Participation\EligibleContributions;
use App\Services\Participation\ParticipationOutbox;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * When an answer is accepted by somebody else, or a solution is verified by a moderator, and the person it belongs to has
 * switched on "let Dot.Memory remember the topics I help with", queue one participation event per topic of the item.
 *
 * For everyone else this does nothing at all. It never fails the request that caused it, and it never retracts anything:
 * Memory has no per-event delete, so a later revoke or hide leaves the count until the person turns sharing off or
 * Memory's retention removes it (said plainly on the Dot Points page).
 */
class ForwardKnowledgeEventToMemory
{
    public function __construct(private readonly ParticipationOutbox $outbox) {}

    public function handle(object $event): void
    {
        if (! $event instanceof AnswerAccepted && ! $event instanceof SolutionVerified) {
            return;
        }

        try {
            $uuid = $event->facts['author_uuid'] ?? null;
            $user = is_string($uuid) ? User::where('uuid', $uuid)->first() : null;
            if ($user === null || ! $user->sharesExpertise()) {
                return;
            }

            $queued = 0;
            foreach (EligibleContributions::forUser($user, $event->ref) as $participation) {
                $queued += $this->outbox->enqueue($user, $participation) ? 1 : 0;
            }

            if ($queued > 0) {
                // Deliver now, after the response is sent, so nobody waits on Memory; the scheduled flush is the safety net.
                dispatch(fn () => app(ParticipationOutbox::class)->flush(10))->afterResponse();
            }
        } catch (Throwable $e) {
            Log::warning('participation.enqueue_failed', ['exception' => $e::class]);
        }
    }
}
