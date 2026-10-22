<?php

namespace App\Http\Controllers;

use App\Models\Answer;
use App\Models\Questions;
use App\Models\Solutions;
use App\Models\Topic;
use App\Support\Knowledge\TopicStats;
use Illuminate\Contracts\View\View;

/**
 * Browse by topic. The counts on the index are public ones (published to everyone, not hidden), the same for every
 * visitor. A topic page lists what the signed-in person may see under it, by the usual visibility rules.
 */
class TopicsController extends Controller
{
    private const LIST_LIMIT = 50;

    public function index(): View
    {
        $counts = TopicStats::publicCounts();
        $topics = Topic::active()->ordered()->get();

        return view('topics.index', [
            'topics' => $topics,
            'counts' => $counts,
            'untagged' => [
                'questions' => Questions::withoutGlobalScopes()->openToAll()->untagged()->count(),
                'solutions' => Solutions::withoutGlobalScopes()->openToAll()->untagged()->count(),
            ],
        ]);
    }

    public function show(Topic $topic): View
    {
        $counts = TopicStats::forTopic($topic->id);

        $questions = $topic->questions()
            ->with(['user', 'topics'])
            ->withCount(['comments', 'answers' => fn ($q) => $q->where('status', Answer::PUBLISHED)])
            ->latest('questions.created_at')->latest('questions.id')
            ->limit(self::LIST_LIMIT)->get();

        $solutions = $topic->solutions()
            ->with(['user', 'topics'])
            ->latest('solutions.created_at')->latest('solutions.id')
            ->limit(self::LIST_LIMIT)->get();

        return view('topics.show', [
            'topic' => $topic,
            'counts' => $counts,
            'questions' => $questions,
            'solutions' => $solutions,
            'questionTotal' => $topic->questions()->count(),
            'solutionTotal' => $topic->solutions()->count(),
        ]);
    }
}
