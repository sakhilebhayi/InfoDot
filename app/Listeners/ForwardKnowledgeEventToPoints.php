<?php

namespace App\Listeners;

use App\Events\Knowledge\AnswerAcceptanceRevoked;
use App\Events\Knowledge\AnswerAccepted;
use App\Events\Knowledge\AnswerPublished;
use App\Events\Knowledge\QuestionSubmitted;
use App\Models\Team;
use App\Services\Points\RewardOutbox;
use Illuminate\Support\Str;

/**
 * Turns what happened in InfoDot's Q&A into an event for the points ledger and parks it in the durable outbox.
 * The mapping is the only place that decides what InfoDot tells the ledger; the ledger decides what it is worth.
 */
class ForwardKnowledgeEventToPoints
{
    public function __construct(private readonly RewardOutbox $outbox) {}

    public function handle(object $event): void
    {
        $envelope = match (true) {
            $event instanceof QuestionSubmitted => $this->envelope('infodot.question.published', $event, $event->facts['author_uuid'], null, ['approved' => false]),
            $event instanceof AnswerPublished => $this->envelope('infodot.answer.published', $event, $event->facts['author_uuid'], null, [
                'question_ref' => 'infodot:question:'.$event->facts['question_id'],
                'self_answer' => (bool) $event->facts['self_answer'],
            ]),
            $event instanceof AnswerAccepted => $this->envelope('infodot.answer.accepted', $event, $event->facts['author_uuid'], $event->facts['accepted_by_uuid'], [
                'by_moderator' => (bool) $event->facts['by_moderator'],
            ]),
            $event instanceof AnswerAcceptanceRevoked => $this->envelope('infodot.contribution.reward_reversed', $event, $event->facts['author_uuid'], $event->facts['revoked_by_uuid'] ?? null, [
                'rule_key' => 'infodot.answer.accepted',
                'reason' => 'The acceptance was removed.',
            ]),
            default => null,
        };

        if ($envelope !== null) {
            $this->outbox->enqueue($envelope);

            // Deliver now, after the response is sent, so a person never waits on the ledger; the scheduled flush is the safety net.
            dispatch(fn () => app(RewardOutbox::class)->flush(10))->afterResponse();
        }
    }

    /** @param array<string, mixed> $facts */
    private function envelope(string $type, object $event, string $userUuid, ?string $actorUuid, array $facts): array
    {
        $teamId = $event->facts['tenant_team_id'] ?? null;

        return array_filter([
            'event_id' => (string) Str::uuid(),
            'type' => $type,
            'occurred_at' => now()->toIso8601String(),
            'user_uuid' => $userUuid,
            'contribution_ref' => $event->ref,
            'actor_uuid' => $actorUuid,
            'tenant_uuid' => $teamId ? Team::withoutGlobalScopes()->whereKey($teamId)->value('uuid') : null,
            'visibility' => $event->facts['visibility'] ?? 'public',
            'facts' => $facts,
        ], fn ($v) => $v !== null);
    }
}
