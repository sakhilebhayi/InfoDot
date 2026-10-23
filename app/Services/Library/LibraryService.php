<?php

namespace App\Services\Library;

use App\Events\Knowledge\KnowledgeAdopted;
use App\Events\Knowledge\LibraryEntryWithdrawn;
use App\Models\Answer;
use App\Models\LibraryEntry;
use App\Models\LibraryOutcome;
use App\Models\ModerationAction;
use App\Models\Solutions;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * The rules of the Verified library. Like ModerationService this is where they live: the screens only ask. Adoption is a
 * moderator decision only: Dot.Brain recommends, a moderator other than the author decides, and every decision is written
 * to the append-only moderation trail in the same transaction as the change. Nothing here trusts what a browser or a
 * recommendation says: eligibility is re-checked against the stored content, under a lock, at the moment of the decision.
 * Each action is safe to repeat: the second time it changes nothing, writes no second audit row and raises no second event.
 */
class LibraryService
{
    public const REASON_MIN = 5;

    public const REASON_MAX = 1000;

    /** The answer or solution a reference names, whatever its state (hidden and deleted included). Null if it does not exist. */
    public function find(string $ref): ?Model
    {
        $parsed = LibraryEntry::parseRef($ref);

        if ($parsed === null) {
            return null;
        }

        [$kind, $id] = $parsed;
        $class = $kind === LibraryEntry::ANSWER ? Answer::class : Solutions::class;

        return $class::withoutGlobalScopes()->find($id);
    }

    public static function refOf(Model $item): string
    {
        return LibraryEntry::refFor($item instanceof Answer ? LibraryEntry::ANSWER : LibraryEntry::SOLUTION, $item->getKey());
    }

    /**
     * Who corroborated the item, and when: the person other than the author who accepted the answer, or the moderator
     * other than the author who verified the solution. Null if nobody independent has.
     *
     * @return array{by: User|null, at: Carbon, label: string}|null
     */
    public function corroboration(Model $item): ?array
    {
        if ($item instanceof Answer) {
            return $item->accepted_at !== null && $item->accepted_by !== null && (int) $item->accepted_by !== (int) $item->user_id
                ? ['by' => $item->acceptedBy, 'at' => $item->accepted_at, 'label' => 'Accepted by']
                : null;
        }

        if ($item instanceof Solutions) {
            return $item->verified_at !== null && $item->verified_by !== null && (int) $item->verified_by !== (int) $item->user_id
                ? ['by' => $item->verifier, 'at' => $item->verified_at, 'label' => 'Verified by']
                : null;
        }

        return null;
    }

    /** Why this item cannot be in the library right now, in words for a moderator; null when it can. */
    public function problem(?Model $item): ?string
    {
        if ($item instanceof Answer) {
            $question = $item->question;

            return match (true) {
                $item->trashed() => 'This answer has been deleted.',
                $item->status !== Answer::PUBLISHED => 'This answer is not published.',
                $item->isHidden() || $question === null || $question->isHidden() => 'This answer, or its question, is hidden.',
                $question->visibility !== $question::VISIBILITY_PUBLIC => 'This answer belongs to a company-only question. Only public knowledge goes in the library.',
                $this->corroboration($item) === null => 'No one other than its author has accepted this answer yet.',
                default => null,
            };
        }

        if ($item instanceof Solutions) {
            return match (true) {
                $item->isHidden() => 'This solution is hidden.',
                $item->visibility !== $item::VISIBILITY_PUBLIC => 'This solution is company-only. Only public knowledge goes in the library.',
                $this->corroboration($item) === null => 'No moderator other than its author has verified this solution yet.',
                default => null,
            };
        }

        return 'That is not an answer or a solution that exists.';
    }

    /**
     * Put an answer or a solution in the library, or answer a Dot.Brain recommendation by doing so.
     *
     * The moderator must not be its author. The item must still be public, published and not hidden, and corroborated by
     * someone other than its author: all re-checked here under a lock, whatever the screen showed. The first adoption
     * writes the entry, one audit row and raises one KnowledgeAdopted event (the points ledger pays the author, once). A
     * repeat changes nothing. Adopting a withdrawn entry again brings it back as a new adoption. When it answers a
     * recommendation, the outcome is queued for Dot.Memory in the same transaction.
     *
     * @return array{entry: LibraryEntry, added: bool} added is false when it was already in the library
     */
    public function adopt(Model $item, User $by, ?string $decisionEventId = null): array
    {
        $this->authorize($item, $by);

        try {
            return $this->adoptOnce($item, $by, $decisionEventId);
        } catch (UniqueConstraintViolationException) {
            // Two moderators adopted the same thing at the same moment; the other one's entry stands.
            $entry = LibraryEntry::where('contribution_ref', self::refOf($item))->firstOrFail();

            return ['entry' => $entry, 'added' => false];
        }
    }

    private function adoptOnce(Model $item, User $by, ?string $decisionEventId): array
    {
        return DB::transaction(function () use ($item, $by, $decisionEventId): array {
            $row = $this->lock($item);
            $ref = self::refOf($row);

            if ($decisionEventId !== null && ($earlier = LibraryOutcome::where('decision_event_id', $decisionEventId)->first()) && $earlier->outcome === LibraryOutcome::DECLINED) {
                throw ValidationException::withMessages(['adopt' => 'This recommendation was already declined.']);
            }

            $entry = LibraryEntry::where('contribution_ref', $ref)->lockForUpdate()->first();

            if ($entry !== null && ! $entry->isWithdrawn()) {
                if ($decisionEventId !== null) {
                    $this->queueOutcome($decisionEventId, $ref, LibraryOutcome::ADOPTED, $by);
                }

                return ['entry' => $entry, 'added' => false];
            }

            if ($problem = $this->problem($row)) {
                throw ValidationException::withMessages(['adopt' => $problem]);
            }

            $values = ['adopted_by' => $by->id, 'adopted_at' => now(), 'recommendation_event_id' => $decisionEventId, 'withdrawn_at' => null];

            if ($entry === null) {
                $entry = LibraryEntry::create($values + [
                    'contribution_ref' => $ref,
                    'kind' => $row instanceof Answer ? LibraryEntry::ANSWER : LibraryEntry::SOLUTION,
                    'answer_id' => $row instanceof Answer ? $row->getKey() : null,
                    'solution_id' => $row instanceof Solutions ? $row->getKey() : null,
                ]);
            } else {
                $entry->forceFill($values)->save();
            }

            $action = ModerationAction::record($row, $by, ModerationAction::LIBRARY_ADOPTED, $decisionEventId !== null ? 'Recommended by Dot.Brain.' : 'Added by a moderator without a recommendation.');

            if ($decisionEventId !== null) {
                $this->queueOutcome($decisionEventId, $ref, LibraryOutcome::ADOPTED, $by);
            }

            $this->tellLedger(new KnowledgeAdopted($ref, $this->ledgerFacts($row, $action) + [
                'adopted_by_uuid' => $by->uuid, 'pack_ref' => 'library:'.$entry->getKey(),
            ]));

            return ['entry' => $entry, 'added' => true];
        });
    }

    /**
     * A moderator turns a recommendation down. No reason is asked; the decision and who made it are recorded, and Memory is
     * told. The moderator must not be the author. Repeating it changes nothing.
     *
     * @return bool false when this recommendation had already been decided
     */
    public function decline(string $decisionEventId, string $contributionRef, User $by): bool
    {
        abort_unless($by->isModerator(), 403);
        $item = $this->find($contributionRef);

        if ($item !== null) {
            abort_if($item->user_id === $by->id, 403, 'You cannot decide on your own content: another moderator does.');
        }

        try {
            return DB::transaction(function () use ($decisionEventId, $contributionRef, $by, $item): bool {
                if (LibraryOutcome::where('decision_event_id', $decisionEventId)->lockForUpdate()->exists()) {
                    return false;
                }

                $this->queueOutcome($decisionEventId, $contributionRef, LibraryOutcome::DECLINED, $by);

                if ($item !== null) {
                    ModerationAction::record($item, $by, ModerationAction::LIBRARY_DECLINED, 'Recommended by Dot.Brain, declined.');
                }

                return true;
            });
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    /**
     * Take an entry out of the library. A reason is required and is kept in the trail. The points the adoption earned
     * are taken back, once. The moderator must not be the author.
     *
     * @return bool false when it was already withdrawn
     */
    public function withdraw(LibraryEntry $entry, User $by, string $reason): bool
    {
        abort_unless($by->isModerator(), 403);

        $item = $entry->source();
        abort_if($item === null, 404);
        $this->authorize($item, $by);

        $reason = trim($reason);
        if (mb_strlen($reason) < self::REASON_MIN || mb_strlen($reason) > self::REASON_MAX) {
            throw ValidationException::withMessages(['withdraw' => 'Say why it is withdrawn, in at least '.self::REASON_MIN.' characters.']);
        }

        return DB::transaction(function () use ($entry, $by, $reason, $item): bool {
            $fresh = LibraryEntry::lockForUpdate()->findOrFail($entry->getKey());

            if ($fresh->isWithdrawn()) {
                return false;
            }

            $row = $this->lock($item);
            $fresh->forceFill(['withdrawn_at' => now()])->save();
            $action = ModerationAction::record($row, $by, ModerationAction::LIBRARY_WITHDRAWN, $reason);

            $this->tellLedger(new LibraryEntryWithdrawn($fresh->contribution_ref, $this->ledgerFacts($row, $action) + [
                'withdrawn_by_uuid' => $by->uuid, 'pack_ref' => 'library:'.$fresh->getKey(),
            ]));

            return true;
        });
    }

    /** A moderator, and not the author of what they decide on. */
    private function authorize(Model $item, User $by): void
    {
        abort_unless($by->isModerator(), 403);
        abort_unless($item instanceof Answer || $item instanceof Solutions, 404);
        abort_if($item->user_id === $by->id, 403, 'You cannot decide on your own content: another moderator does.');
    }

    /** The item as stored, locked for the length of the transaction, ignoring what the caller was allowed to see. */
    private function lock(Model $item): Model
    {
        return $item::withoutGlobalScopes()->lockForUpdate()->findOrFail($item->getKey());
    }

    /**
     * Keep the decision for Dot.Memory in the same transaction as the decision itself. It is sent right after the response
     * (so nobody waits on Memory); the scheduled `library:flush-outcomes` is the safety net, and an outage only delays it.
     */
    private function queueOutcome(string $decisionEventId, string $ref, string $outcome, User $by): void
    {
        LibraryOutcome::firstOrCreate(['decision_event_id' => $decisionEventId], [
            'contribution_ref' => $ref, 'outcome' => $outcome, 'actor_id' => $by->id, 'actor_ref' => (string) $by->uuid, 'next_attempt_at' => now(),
        ]);

        // defer() runs after the response without serializing the closure (a closure nested in a queued closure hangs the serializer).
        DB::afterCommit(static fn () => defer(static fn () => app(LibraryOutcomeOutbox::class)->flush(10)));
    }

    /** What the points ledger needs to know about an item, as ids: who wrote it, where it lives, and which audit row caused this. */
    private function ledgerFacts(Model $row, ModerationAction $action): array
    {
        $home = $row instanceof Answer ? $row->question : $row;

        return [
            'author_uuid' => $row->user->uuid,
            'tenant_team_id' => $home->team_id,
            'visibility' => 'public',
            'event_key' => 'moderation_action:'.$action->id,
        ];
    }

    /** Raise a ledger-bound event once the change is committed. */
    private function tellLedger(object $event): void
    {
        DB::afterCommit(fn () => event($event));
    }
}
