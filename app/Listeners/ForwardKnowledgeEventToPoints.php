<?php

namespace App\Listeners;

use App\Events\Knowledge\AnswerAcceptanceRevoked;
use App\Events\Knowledge\AnswerAccepted;
use App\Events\Knowledge\AnswerPublished;
use App\Events\Knowledge\ContentReported;
use App\Events\Knowledge\ContributionApproved;
use App\Events\Knowledge\QuestionSubmitted;
use App\Events\Knowledge\SolutionUnverified;
use App\Events\Knowledge\SolutionVerified;
use App\Models\Team;
use App\Services\Points\RewardOutbox;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

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
            $event instanceof SolutionVerified => $this->envelope('infodot.solution.verified', $event, $event->facts['author_uuid'], $event->facts['verified_by_uuid'], ['kind' => 'solution']),
            $event instanceof SolutionUnverified => $this->envelope('infodot.contribution.reward_reversed', $event, $event->facts['author_uuid'], $event->facts['unverified_by_uuid'], [
                'rule_key' => 'infodot.solution.verified',
                'reason' => 'The verification was removed.',
            ]),
            $event instanceof ContributionApproved => $this->envelope('infodot.contribution.approved', $event, $event->facts['author_uuid'], $event->facts['approved_by_uuid'], ['kind' => $event->facts['kind']]),
            // Only the reason travels: who reported it stays in InfoDot.
            $event instanceof ContentReported => $this->envelope('infodot.contribution.flagged', $event, $event->facts['author_uuid'], null, ['reason' => $event->facts['reason']]),
            default => null,
        };

        if ($envelope !== null) {
            $this->outbox->enqueue($envelope);

            // Deliver now, after the response is sent, so a person never waits on the ledger; the scheduled flush is the safety net.
            dispatch(fn () => app(RewardOutbox::class)->flush(10))->afterResponse();
        }
    }

    /**
     * An event that names the audit row behind it (`event_key`) gets an id derived from the action, so replaying the same
     * action can never produce a second ledger event, while a later, different action (verify again after an unverify)
     * gets its own. Events without a key keep a random id, as before.
     *
     * @param  array<string, mixed>  $facts
     */
    private function envelope(string $type, object $event, string $userUuid, ?string $actorUuid, array $facts): array
    {
        $teamId = $event->facts['tenant_team_id'] ?? null;
        $key = $event->facts['event_key'] ?? null;

        return array_filter([
            'event_id' => $key !== null ? Uuid::uuid5(Uuid::NAMESPACE_URL, 'infodot:'.$type.':'.$event->ref.':'.$key)->toString() : (string) Str::uuid(),
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
