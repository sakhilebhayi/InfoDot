<?php

namespace App\Services\Knowledge;

use App\Events\Knowledge\AnswerAcceptanceRevoked;
use App\Events\Knowledge\AnswerAccepted;
use App\Events\Knowledge\AnswerPublished;
use App\Events\Knowledge\ContentReported;
use App\Models\Answer;
use App\Models\ContentReport;
use App\Models\Questions;
use App\Models\Solutions;
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

    public const REPORTS_PER_HOUR = 10;

    /** May this person see (and so answer) this question? Public, their own, or their company's. */
    public function canSee(Questions $question, User $user): bool
    {
        return $question->isPublic()
            || $question->user_id === $user->id
            || ($question->team_id !== null && $user->belongsToTeam($question->team));
    }

    /** May this person see this question, answer or solution (which also decides whether they may report it)? */
    public function canSeeContent(Model $item, User $user): bool
    {
        return match (true) {
            $item instanceof Answer => $this->canSee($item->question, $user),
            $item instanceof Questions => $this->canSee($item, $user),
            $item instanceof Solutions => $item->isPublic()
                || $item->user_id === $user->id
                || ($item->team_id !== null && $user->belongsToTeam($item->team)),
            default => false,
        };
    }

    public function publish(Questions $question, User $author, string $body): Answer
    {
        $body = trim($body);

        abort_unless($this->canSee($question, $author), 403);
        abort_if($question->isHidden(), 403);

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
            && ! $answer->isHidden()
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
            // Two people accepting at once (the asker and a moderator, or a double click) must not leave two accepted answers or send two
            // credits: take the question's row first, then look again at what is accepted.
            Questions::withoutGlobalScopes()->whereKey($answer->question_id)->lockForUpdate()->first();

            if (Answer::withoutGlobalScopes()->whereKey($answer->id)->whereNotNull('accepted_at')->exists()) {
                return;
            }

            $previous = Answer::withoutGlobalScopes()->where('question_id', $answer->question_id)->whereNotNull('accepted_at')->get();

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

    /**
     * Anyone who can see an item may report it, once, but never their own. Reports go to the moderators; nothing is removed
     * automatically. A person can send REPORTS_PER_HOUR new reports an hour. Reporting the same thing again is not an error:
     * the first report stands and nothing new is created.
     */
    public function report(Model $item, User $reporter, string $reason, ?string $detail = null): ContentReport
    {
        abort_unless(in_array($reason, ContentReport::REASONS, true), 422);
        abort_unless($item instanceof Answer || $item instanceof Questions || $item instanceof Solutions, 422);
        abort_unless($this->canSeeContent($item, $reporter), 403);

        if ($item->user_id === $reporter->id) {
            throw ValidationException::withMessages(['report' => 'You cannot report your own content. If it is wrong, say so in a comment or ask a moderator.']);
        }

        if ($item->isHidden()) {
            throw ValidationException::withMessages(['report' => 'A moderator has already hidden this.']);
        }

        $morph = ['reportable_type' => $item->getMorphClass(), 'reportable_id' => $item->getKey(), 'reporter_id' => $reporter->id];

        if ($existing = ContentReport::where($morph)->first()) {
            return $existing;
        }

        $key = 'reports:'.$reporter->id;

        if (RateLimiter::tooManyAttempts($key, self::REPORTS_PER_HOUR)) {
            $minutes = max(1, (int) ceil(RateLimiter::availableIn($key) / 60));

            throw ValidationException::withMessages(['report' => 'You have sent '.self::REPORTS_PER_HOUR.' reports in the last hour. Try again in '.$minutes.' '.($minutes === 1 ? 'minute' : 'minutes').'.']);
        }

        $report = ContentReport::firstOrCreate(
            $morph + ['status' => ContentReport::OPEN],
            ['reason' => $reason, 'detail' => $detail !== null ? mb_substr(trim($detail), 0, 1000) : null],
        );

        if ($report->wasRecentlyCreated) {
            RateLimiter::hit($key, 3600);

            $question = $item instanceof Answer ? $item->question : $item;

            event(new ContentReported(match (true) {
                $item instanceof Answer => $item->reference(),
                $item instanceof Solutions => 'infodot:solution:'.$item->getKey(),
                default => 'infodot:question:'.$item->getKey(),
            }, [
                'reason' => $reason,
                'report_id' => $report->id,
                // Who wrote it and where it lives, for the points ledger. The reporter is deliberately not part of the event's facts.
                'author_uuid' => $item->user->uuid,
                'tenant_team_id' => $question->team_id,
                'visibility' => $question->visibility,
            ]));
        }

        return $report;
    }
}
