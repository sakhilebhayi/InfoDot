<?php

namespace App\Support\Knowledge;

use App\Models\Answer;
use App\Models\Questions;
use App\Models\Solutions;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * "Questions you might be able to answer", for the signed-in person only and only when they switched it on.
 *
 * Where the person has shown they know the subject: a topic counts when an answer of theirs was accepted by somebody
 * else on a public question filed under it, or a public solution of theirs filed under it was verified by a moderator
 * other than them (the same rules as their achievements). Then: open public questions in those topics that nobody has
 * answered yet, newest first, never their own, at most five. A plain read of existing data. It sends nothing, notifies
 * nobody, ranks nobody and is shown to nobody else.
 */
final class TopicSuggestions
{
    public const LIMIT = 5;

    /**
     * The topics (still on) where this person has an accepted answer or a verified solution.
     *
     * @return Collection<int, Topic>
     */
    public static function expertiseTopics(User $user): Collection
    {
        $publicQuestions = Questions::withoutGlobalScopes()->openToAll()->select('questions.id');

        $viaAnswers = Answer::withoutGlobalScope('moderation-visibility')
            ->where('answers.user_id', $user->id)->where('answers.status', Answer::PUBLISHED)->whereNull('answers.hidden_at')
            ->where('answers.is_self_answer', false)
            ->whereNotNull('answers.accepted_at')->whereNotNull('answers.accepted_by')->where('answers.accepted_by', '!=', $user->id)
            ->whereIn('answers.question_id', $publicQuestions)
            ->join('question_topic', 'question_topic.question_id', '=', 'answers.question_id')
            ->select('question_topic.topic_id');

        $viaSolutions = Solutions::withoutGlobalScopes()->openToAll()
            ->where('solutions.user_id', $user->id)
            ->whereNotNull('solutions.verified_at')->whereNotNull('solutions.verified_by')->where('solutions.verified_by', '!=', $user->id)
            ->join('solution_topic', 'solution_topic.solution_id', '=', 'solutions.id')
            ->select('solution_topic.topic_id');

        $ids = $viaAnswers->pluck('topic_id')->merge($viaSolutions->pluck('topic_id'))->unique()->values();

        return Topic::active()->ordered()->whereIn('id', $ids)->get();
    }

    /**
     * @param  Collection<int, Topic>|null  $topics  the person's expertise topics, when already known
     * @return Collection<int, Questions>
     */
    public static function questionsFor(User $user, ?Collection $topics = null): Collection
    {
        $topics ??= self::expertiseTopics($user);

        if ($topics->isEmpty()) {
            return new Collection;
        }

        return Questions::withoutGlobalScopes()->openToAll()->unanswered()
            ->where('questions.user_id', '!=', $user->id)
            ->whereIn('questions.id', fn ($sub) => $sub->select('question_id')->from('question_topic')->whereIn('topic_id', $topics->pluck('id')))
            ->with('topics')
            ->orderByDesc('questions.created_at')->orderByDesc('questions.id')
            ->limit(self::LIMIT)
            ->get(['questions.id', 'questions.question', 'questions.created_at']);
    }
}
