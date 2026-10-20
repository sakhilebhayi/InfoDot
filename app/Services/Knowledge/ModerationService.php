<?php

namespace App\Services\Knowledge;

use App\Models\Answer;
use App\Models\ContentReport;
use App\Models\ModerationAction;
use App\Models\ModerationAppeal;
use App\Models\Questions;
use App\Models\Solutions;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * What moderators may do, and what the author may do about it. Like AnswerService this is where the rules live: the
 * screens only ask. Every decision writes a row to the append-only moderation trail in the same transaction as the change,
 * so there is never a change without its record. Each action is safe to repeat: doing it twice changes nothing the second
 * time (a second hide does not reverse the reward again, a second dismissal writes no second row).
 */
class ModerationService
{
    public const NOTE_MIN = 5;

    public const NOTE_MAX = 1000;

    /** The short names the screens use for the three kinds of content. */
    /** The scopes that decide who sees what; soft deletes stay in force. */
    private const VISIBILITY_SCOPES = ['moderation-visibility', 'knowledge-visibility'];

    public const KINDS = ['question' => Questions::class, 'answer' => Answer::class, 'solution' => Solutions::class];

    public function __construct(private readonly AnswerService $answers) {}

    public static function kindOf(Model $item): string
    {
        return array_search($item::class, self::KINDS, true) ?: throw new \InvalidArgumentException('Not moderated content: '.$item::class);
    }

    /** The item named by a short kind and id, as the signed-in person is allowed to see it. */
    public function find(string $kind, int $id): Model
    {
        $class = self::KINDS[$kind] ?? null;
        abort_if($class === null, 404);

        return $class::query()->findOrFail($id);
    }

    /** The item a content report or audit row points at, whatever the person may see. Null if it no longer exists. */
    public function resolve(string $morphType, int $id): ?Model
    {
        $class = Relation::getMorphedModel($morphType) ?? $morphType;

        if (! in_array($class, self::KINDS, true)) {
            return null;
        }

        return $class::withoutGlobalScopes(self::VISIBILITY_SCOPES)->find($id);
    }

    /** A moderator, for an item they can see, that is not their own. */
    public function canModerate(Model $item, User $by): bool
    {
        return $by->isModerator()
            && $item->user_id !== $by->id
            && $this->answers->canSeeContent($item, $by);
    }

    private function authorize(Model $item, User $by): void
    {
        abort_unless($by->isModerator(), 403);
        abort_unless($this->answers->canSeeContent($item, $by), 403);
        abort_if($item->user_id === $by->id, 403, 'You cannot moderate your own content.');
    }

    private function cleanNote(?string $note, string $field, bool $required, string $message): ?string
    {
        $note = trim((string) $note);

        if ($note === '' && ! $required) {
            return null;
        }

        if (mb_strlen($note) < self::NOTE_MIN || mb_strlen($note) > self::NOTE_MAX) {
            throw ValidationException::withMessages([$field => $message]);
        }

        return $note;
    }

    /** The item as stored, locked for the length of the transaction, ignoring what the caller was allowed to see. */
    private function lock(Model $item): Model
    {
        return $item::withoutGlobalScopes(self::VISIBILITY_SCOPES)->lockForUpdate()->findOrFail($item->getKey());
    }

    /** @return Collection<int, ContentReport> */
    private function openReports(Model $item): Collection
    {
        return ContentReport::where('reportable_type', $item->getMorphClass())
            ->where('reportable_id', $item->getKey())
            ->where('status', ContentReport::OPEN)
            ->orderBy('id')
            ->get();
    }

    private function resolveReports(Collection $reports, string $status, User $by, ?string $note): void
    {
        foreach ($reports as $report) {
            $report->forceFill(['status' => $status, 'resolved_by' => $by->id, 'resolved_at' => now(), 'resolution_note' => $note])->save();
        }
    }

    /**
     * Hide the content. The reason is shown to its author. Every open report on it is resolved as actioned. Hiding an
     * accepted answer removes its acceptance through AnswerService, which tells the points ledger to reverse the award once.
     *
     * @return bool false when it was already hidden (nothing happened)
     */
    public function hide(Model $item, User $by, string $reason): bool
    {
        $this->authorize($item, $by);
        $reason = $this->cleanNote($reason, 'reason', true, 'Say why it is hidden, in at least '.self::NOTE_MIN.' characters. The author will read this.');

        return DB::transaction(function () use ($item, $by, $reason): bool {
            $row = $this->lock($item);

            if ($row->isHidden()) {
                return false;
            }

            $reports = $this->openReports($row);
            $wasAccepted = $row instanceof Answer && $row->isAccepted();

            $row->forceFill(['hidden_at' => now(), 'hidden_by' => $by->id, 'hidden_reason' => $reason])->save();
            ModerationAction::record($row, $by, ModerationAction::HIDDEN, $reason, $reports->last()?->id);
            $this->resolveReports($reports, ContentReport::ACTIONED, $by, $reason);

            if ($wasAccepted) {
                $this->answers->revokeAcceptance($row, $by);
                ModerationAction::record($row, $by, ModerationAction::ACCEPTANCE_REVOKED, 'Hidden by a moderator: '.$reason);
            }

            return true;
        });
    }

    /**
     * Dismiss every open report on the item: it stays as it is.
     *
     * @return int how many reports were dismissed (0 when there was nothing open)
     */
    public function dismiss(Model $item, User $by, string $note): int
    {
        $this->authorize($item, $by);
        $note = $this->cleanNote($note, 'note', true, 'Say why the reports are dismissed, in at least '.self::NOTE_MIN.' characters.');

        return DB::transaction(function () use ($item, $by, $note): int {
            $row = $this->lock($item);
            $reports = $this->openReports($row);

            if ($reports->isEmpty()) {
                return 0;
            }

            ModerationAction::record($row, $by, ModerationAction::DISMISSED, $note, $reports->last()->id);
            $this->resolveReports($reports, ContentReport::DISMISSED, $by, $note);

            return $reports->count();
        });
    }

    /**
     * Show hidden content again. It does not bring a removed acceptance back: the asker accepts again if they still agree.
     * An appeal still waiting on it is closed as upheld, since the author has what they asked for.
     *
     * @return bool false when it was not hidden
     */
    public function restore(Model $item, User $by, ?string $note = null): bool
    {
        $this->authorize($item, $by);
        $note = $this->cleanNote($note, 'note', false, 'A note must be at least '.self::NOTE_MIN.' characters, or left empty.');

        return DB::transaction(function () use ($item, $by, $note): bool {
            $row = $this->lock($item);

            if (! $row->isHidden()) {
                return false;
            }

            $this->unhide($row);
            ModerationAction::record($row, $by, ModerationAction::RESTORED, $note);

            ModerationAppeal::where('subject_type', $row->getMorphClass())->where('subject_id', $row->getKey())->where('status', ModerationAppeal::OPEN)
                ->get()
                ->each(fn (ModerationAppeal $appeal) => $appeal->forceFill([
                    'status' => ModerationAppeal::UPHELD, 'decided_by' => $by->id, 'decided_at' => now(),
                    'decision_note' => 'A moderator restored the content before this appeal was decided.',
                ])->save());

            return true;
        });
    }

    private function unhide(Model $row): void
    {
        $row->forceFill(['hidden_at' => null, 'hidden_by' => null, 'hidden_reason' => null])->save();
    }

    /** The author of hidden content may appeal it, once per hide. */
    public function fileAppeal(Model $item, User $author, string $body): ModerationAppeal
    {
        abort_unless($item->user_id === $author->id, 403);
        $body = $this->cleanNote($body, 'appeal', true, 'Tell the moderators why this should be shown again, in at least '.self::NOTE_MIN.' characters.');

        try {
            return DB::transaction(function () use ($item, $author, $body): ModerationAppeal {
                $row = $this->lock($item);

                if (! $row->isHidden()) {
                    throw ValidationException::withMessages(['appeal' => 'This is not hidden, so there is nothing to appeal.']);
                }

                $hide = ModerationAction::where('subject_type', $row->getMorphClass())->where('subject_id', $row->getKey())
                    ->where('action', ModerationAction::HIDDEN)->orderByDesc('id')->first();

                if ($hide === null) {
                    throw ValidationException::withMessages(['appeal' => 'This cannot be appealed because no moderator decision is on record for it.']);
                }

                if (ModerationAppeal::where('subject_type', $row->getMorphClass())->where('subject_id', $row->getKey())->where('hide_action_id', $hide->id)->exists()) {
                    throw ValidationException::withMessages(['appeal' => 'You have already appealed this decision. An appeal can be made once.']);
                }

                return ModerationAppeal::create([
                    'subject_type' => $row->getMorphClass(), 'subject_id' => $row->getKey(), 'hide_action_id' => $hide->id,
                    'appellant_id' => $author->id, 'hidden_by' => $row->hidden_by, 'body' => $body, 'status' => ModerationAppeal::OPEN,
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['appeal' => 'You have already appealed this decision. An appeal can be made once.']);
        }
    }

    /** May this moderator decide this appeal? Not the one who hid it, not the author, and only while it is open. */
    public function canDecide(ModerationAppeal $appeal, User $by): bool
    {
        return $by->isModerator()
            && $appeal->isOpen()
            && $appeal->hidden_by !== $by->id
            && $appeal->appellant_id !== $by->id;
    }

    /**
     * Uphold (the content is shown again) or deny (it stays hidden; a note is required). A decision is final: an appeal
     * that has been decided is never decided again, and the author cannot appeal again.
     */
    public function decideAppeal(ModerationAppeal $appeal, User $by, bool $uphold, ?string $note): ModerationAppeal
    {
        abort_unless($by->isModerator(), 403);
        abort_if($appeal->hidden_by === $by->id, 403, 'A different moderator must decide this appeal: you hid it.');
        abort_if($appeal->appellant_id === $by->id, 403, 'You cannot decide an appeal on your own content.');

        $note = $this->cleanNote($note, 'note', ! $uphold, 'Say why the appeal is denied, in at least '.self::NOTE_MIN.' characters. The author will read this.');

        return DB::transaction(function () use ($appeal, $by, $uphold, $note): ModerationAppeal {
            $fresh = ModerationAppeal::lockForUpdate()->findOrFail($appeal->id);

            if (! $fresh->isOpen()) {
                throw ValidationException::withMessages(['note' => 'This appeal has already been decided.']);
            }

            $item = $this->resolve($fresh->subject_type, $fresh->subject_id);
            abort_if($item === null, 404);
            abort_unless($this->answers->canSeeContent($item, $by), 403);

            $row = $this->lock($item);

            $fresh->forceFill([
                'status' => $uphold ? ModerationAppeal::UPHELD : ModerationAppeal::DENIED,
                'decided_by' => $by->id, 'decided_at' => now(), 'decision_note' => $note,
            ])->save();

            if ($uphold && $row->isHidden()) {
                $this->unhide($row);
            }

            ModerationAction::record($row, $by, $uphold ? ModerationAction::APPEAL_UPHELD : ModerationAction::APPEAL_DENIED, $note, null, $fresh->id);

            return $fresh;
        });
    }

    /**
     * A moderator other than the author checks that the solution works. Verification is the moderator's own judgement, not
     * a vote: the author never verifies their own solution.
     *
     * @return bool false when it was already verified
     */
    public function verify(Solutions $solution, User $by, ?string $note = null): bool
    {
        $this->authorize($solution, $by);
        $note = $this->cleanNote($note, 'note', false, 'A note must be at least '.self::NOTE_MIN.' characters, or left empty.');

        return DB::transaction(function () use ($solution, $by, $note): bool {
            $row = $this->lock($solution);

            if ($row->isHidden()) {
                throw ValidationException::withMessages(['note' => 'A hidden solution cannot be verified.']);
            }

            if ($row->isVerified()) {
                return false;
            }

            $row->forceFill(['verified_at' => now(), 'verified_by' => $by->id])->save();
            ModerationAction::record($row, $by, ModerationAction::VERIFIED, $note);

            return true;
        });
    }

    /** @return bool false when it was not verified */
    public function unverify(Solutions $solution, User $by, string $reason): bool
    {
        $this->authorize($solution, $by);
        $reason = $this->cleanNote($reason, 'note', true, 'Say why the verification is removed, in at least '.self::NOTE_MIN.' characters.');

        return DB::transaction(function () use ($solution, $by, $reason): bool {
            $row = $this->lock($solution);

            if (! $row->isVerified()) {
                return false;
            }

            $row->forceFill(['verified_at' => null, 'verified_by' => null])->save();
            ModerationAction::record($row, $by, ModerationAction::UNVERIFIED, $reason);

            return true;
        });
    }

    /** Everything moderators have done about an item, oldest first. */
    public function history(Model $item): Collection
    {
        return ModerationAction::with('actor')
            ->where('subject_type', $item->getMorphClass())->where('subject_id', $item->getKey())
            ->orderBy('id')
            ->get();
    }
}
