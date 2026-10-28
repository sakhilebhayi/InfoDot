<?php

namespace App\Listeners;

use App\Events\Knowledge\AnswerAccepted;
use App\Events\Knowledge\AnswerPublished;
use App\Events\Knowledge\KnowledgeAdopted;
use App\Events\Knowledge\SolutionVerified;
use App\Models\Answer;
use App\Models\Questions;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Knowledge\InAppNotices;
use Throwable;

/**
 * Turns a knowledge event into an in-app notice for the person it is about. Runs in the request after the change is saved
 * and can never fail it. Only ids from the event are used; the words are written when the notice is read.
 */
class RecordInAppNotice
{
    public function __construct(private readonly InAppNotices $notices) {}

    public function handle(object $event): void
    {
        try {
            $facts = $event->facts ?? [];

            if ($event instanceof AnswerPublished) {
                $this->answerReceived($event, $facts);
            } elseif ($event instanceof AnswerAccepted) {
                $this->answerAccepted($event, $facts);
            } elseif ($event instanceof SolutionVerified) {
                $this->solutionVerified($event, $facts);
            } elseif ($event instanceof KnowledgeAdopted) {
                $this->knowledgeReused($event, $facts);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function answerReceived(object $event, array $facts): void
    {
        if (! empty($facts['self_answer'])) {
            return;
        }

        $question = Questions::withoutGlobalScopes()->find((int) ($facts['question_id'] ?? 0));
        $author = User::where('uuid', $facts['author_uuid'] ?? null)->first();
        $asker = $question?->user_id ? User::find($question->user_id) : null;

        if ($question === null || $asker === null || $question->isHidden()) {
            return;
        }

        $this->notices->record(UserNotice::ANSWER_RECEIVED, $asker, 'question', $question->id, $event->ref, $author);
    }

    private function answerAccepted(object $event, array $facts): void
    {
        $answer = Answer::withoutGlobalScopes()->find((int) ($facts['answer_id'] ?? 0));
        $author = User::where('uuid', $facts['author_uuid'] ?? null)->first();
        $by = User::where('uuid', $facts['accepted_by_uuid'] ?? null)->first();

        if ($answer === null || $author === null) {
            return;
        }

        $this->notices->record(UserNotice::ANSWER_ACCEPTED, $author, 'question', (int) $answer->question_id, $event->ref, $by);
    }

    private function solutionVerified(object $event, array $facts): void
    {
        $author = User::where('uuid', $facts['author_uuid'] ?? null)->first();
        $by = User::where('uuid', $facts['verified_by_uuid'] ?? null)->first();
        $id = (int) preg_replace('/\D/', '', (string) $event->ref);

        if ($author === null || $id < 1) {
            return;
        }

        $this->notices->record(UserNotice::SOLUTION_VERIFIED, $author, 'solution', $id, $event->ref, $by);
    }

    private function knowledgeReused(object $event, array $facts): void
    {
        $author = User::where('uuid', $facts['author_uuid'] ?? null)->first();
        $by = User::where('uuid', $facts['adopted_by_uuid'] ?? null)->first();
        $ref = (string) $event->ref;
        $id = (int) preg_replace('/\D/', '', $ref);

        if ($author === null || $id < 1) {
            return;
        }

        if (str_starts_with($ref, 'infodot:answer:')) {
            $answer = Answer::withoutGlobalScopes()->find($id);
            if ($answer === null) {
                return;
            }
            $this->notices->record(UserNotice::KNOWLEDGE_REUSED, $author, 'question', (int) $answer->question_id, $ref, $by);
        } elseif (str_starts_with($ref, 'infodot:solution:')) {
            $this->notices->record(UserNotice::KNOWLEDGE_REUSED, $author, 'solution', $id, $ref, $by);
        }
    }
}
