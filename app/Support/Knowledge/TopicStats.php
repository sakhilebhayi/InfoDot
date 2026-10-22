<?php

namespace App\Support\Knowledge;

use App\Models\Questions;
use App\Models\Solutions;
use Illuminate\Support\Collection;

/**
 * The public counts shown on /topics. Public means published to the whole community and not hidden: company-only and
 * hidden items are never counted, and who is looking never changes a number. Answered means a question is solved or has a
 * published answer from somebody other than the asker; unanswered is the rest.
 */
final class TopicStats
{
    /** @return Collection<int, array{questions: int, answered: int, unanswered: int, solutions: int}> keyed by topic id */
    public static function publicCounts(): Collection
    {
        $questions = fn () => Questions::withoutGlobalScopes()->openToAll()
            ->join('question_topic', 'question_topic.question_id', '=', 'questions.id')
            ->selectRaw('question_topic.topic_id as topic_id, count(*) as n')
            ->groupBy('question_topic.topic_id');

        $total = $questions()->pluck('n', 'topic_id');
        $unanswered = $questions()->unanswered()->pluck('n', 'topic_id');
        $solutions = Solutions::withoutGlobalScopes()->openToAll()
            ->join('solution_topic', 'solution_topic.solution_id', '=', 'solutions.id')
            ->selectRaw('solution_topic.topic_id as topic_id, count(*) as n')
            ->groupBy('solution_topic.topic_id')
            ->pluck('n', 'topic_id');

        return $total->keys()->merge($solutions->keys())->unique()->mapWithKeys(function ($id) use ($total, $unanswered, $solutions) {
            $all = (int) ($total[$id] ?? 0);
            $open = (int) ($unanswered[$id] ?? 0);

            return [(int) $id => ['questions' => $all, 'answered' => $all - $open, 'unanswered' => $open, 'solutions' => (int) ($solutions[$id] ?? 0)]];
        });
    }

    /** @return array{questions: int, answered: int, unanswered: int, solutions: int} */
    public static function forTopic(int $topicId): array
    {
        return self::publicCounts()->get($topicId, ['questions' => 0, 'answered' => 0, 'unanswered' => 0, 'solutions' => 0]);
    }
}
