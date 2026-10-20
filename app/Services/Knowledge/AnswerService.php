<?php

namespace App\Services\Knowledge;

use App\Events\Knowledge\AnswerAcceptanceRevoked;
use App\Events\Knowledge\AnswerAccepted;
use App\Events\Knowledge\AnswerPublished;
use App\Events\Knowledge\ContentReported;
use App\Models\Answer;
use App\Models\ContentReport;
use App\Models\Questions;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * The rules of formal answers. Everything that decides who may do what lives here, on the server: the
 * browser only ever asks. Events are raised once, after the change is saved, and carry ids only.
 */
class AnswerService
{
    public const MIN_LENGTH = 20;

    public const MAX_LENGTH = 10000;

    public const MAX_PER_PERSON_PER_QUESTION = 3;

    public const PER_MINUTE = 6;

    /** May this person see (and so answer) this question? Public, their own, or their company's. */
    public function canSee(Questions $question, User $user): bool
    {
        return $question->isPublic()
            || $question->user_id === $user->id
            || ($question->team_id !== null && $user->belongsToTeam($question->team));
    }

    public function publish(Questions $question, User $author, string $body): Answer
    {
        $body = trim($body);

        abort_unless($this->canSee($question, $author), 403);

        if (mb_strlen($body) < self::MIN_LENGTH || mb_strlen($body) > self::MAX_LENGTH) {
            throw ValidationException::withMessages(['body' => 'Write a proper answer: at least '.self::MIN_LENGTH.' characters, with the steps or the reasoning.']);
        }

        if (! RateLimiter::attempt('answers:'.$author->id, self::PER_MINUTE, fn () => true, 60)) {
            throw ValidationException::withMessages(['body' => 'You are posting answers very quickly. Wait a minute and try again.']);
        }

        if (Answer::where('question_id', $question->id)->where('user_id', $author->id)->count() >= self::MAX_PER_PERSON_PER_QUESTION) {
            throw ValidationException::withMessages(['body' => 'You have already answered this question '.self::MAX_PER_PERSON_PER_QUESTION.' times. Edit or follow up on an earlier answer instead.']);
        }

        try {
            $answer = Answer::create([
                'question_id' => $question->id,
                'user_id' => $author->id,
                'body' => $body,
                'body_hash' => Answer::hashBody($body),
                'status' => Answer::PUBLISHED,
                'is_self_answer' => $question->user_id === $author->id,
            ]);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['body' => 'You already posted this answer.']);
        }

        event(new AnswerPublished($answer->reference(), [
            'answer_id' => $answer->id,
            'question_id' => $question->id,
            'author_uuid' => $author->uuid,
            'self_answer' => $answer->is_self_answer,
            'tenant_team_id' => $question->team_id,
            'visibility' => $question->visibility,
        ]));

        return $answer;
    }

    /** The asker or a moderator may accept; never the answer's own author; the answer must be live. */
    public function canAccept(Answer $answer, User $by): bool
    {
        $question = $answer->question;

        return $answer->status === Answer::PUBLISHED
            && $answer->user_id !== $by->id
            && ($question->user_id === $by->id || ($by->isModerator() && $this->canSee($question, $by)));
    }

    public function accept(Answer $answer, User $by): Answer
    {
        abort_unless($this->canAccept($answer, $by), 403);

        if ($answer->isAccepted()) {
            return $answer;
        }

        DB::transaction(function () use ($answer, $by): void {
            $previous = Answer::where('question_id', $answer->question_id)->whereNotNull('accepted_at')->get();

            foreach ($previous as $old) {
                $old->forceFill(['accepted_at' => null, 'accepted_by' => null])->save();
                DB::afterCommit(fn () => event(new AnswerAcceptanceRevoked($old->reference(), ['answer_id' => $old->id, 'author_uuid' => $old->user->uuid, 'revoked_by_uuid' => $by->uuid])));
            }

            $answer->forceFill(['accepted_at' => now(), 'accepted_by' => $by->id])->save();
            $answer->question->forceFill(['status' => 1])->save();

            DB::afterCommit(fn () => event(new AnswerAccepted($answer->reference(), [
                'answer_id' => $answer->id,
                'question_id' => $answer->question_id,
                'author_uuid' => $answer->user->uuid,
                'accepted_by_uuid' => $by->uuid,
                'by_moderator' => $by->isModerator() && $answer->question->user_id !== $by->id,
                'tenant_team_id' => $answer->question->team_id,
                'visibility' => $answer->question->visibility,
            ])));
        });

        return $answer->refresh();
    }

    public function revokeAcceptance(Answer $answer, User $by): Answer
    {
        abort_unless($answer->isAccepted() && ($answer->question->user_id === $by->id || $by->isModerator()), 403);

        DB::transaction(function () use ($answer, $by): void {
            $answer->forceFill(['accepted_at' => null, 'accepted_by' => null])->save();
            $answer->question->forceFill(['status' => 0])->save();
            DB::afterCommit(fn () => event(new AnswerAcceptanceRevoked($answer->reference(), ['answer_id' => $answer->id, 'author_uuid' => $answer->user->uuid, 'revoked_by_uuid' => $by->uuid])));
        });

        return $answer->refresh();
    }

    /** Anyone who can see an item may report it, once. Reports go to the moderators; nothing is removed automatically. */
    public function report(Model $item, User $reporter, string $reason, ?string $detail = null): ContentReport
    {
        abort_unless(in_array($reason, ContentReport::REASONS, true), 422);
        abort_if($item instanceof Answer ? ! $this->canSee($item->question, $reporter) : ($item instanceof Questions && ! $this->canSee($item, $reporter)), 403);

        $report = ContentReport::firstOrCreate(
            ['reportable_type' => $item->getMorphClass(), 'reportable_id' => $item->getKey(), 'reporter_id' => $reporter->id, 'status' => 'open'],
            ['reason' => $reason, 'detail' => $detail !== null ? mb_substr(trim($detail), 0, 1000) : null],
        );

        if ($report->wasRecentlyCreated) {
            event(new ContentReported($item instanceof Answer ? $item->reference() : 'infodot:question:'.$item->getKey(), ['reason' => $reason, 'reporter_uuid' => $reporter->uuid]));
        }

        return $report;
    }
}
