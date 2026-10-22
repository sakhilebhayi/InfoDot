<?php

namespace App\Http\Controllers;

use App\Models\Questions;
use App\Models\Solutions;
use App\Services\Knowledge\TopicService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A moderator corrects which topics a question or a solution is filed under. The rules (1 to 3 topics, nothing newly
 * switched off, not your own content, every change in the trail) are TopicService's; this only reads the form.
 */
class TopicAssignmentController extends Controller
{
    public function question(Request $request, int $qid, TopicService $topics): RedirectResponse
    {
        // Found through the usual scopes: another company's private question is a 404, not a hint that it exists.
        $question = Questions::where('id', $qid)->firstOrFail();

        return $this->apply($request, $question, $topics, route('questions.view', ['qid' => $question->id]));
    }

    public function solution(Request $request, int $id, TopicService $topics): RedirectResponse
    {
        $solution = Solutions::where('id', $id)->firstOrFail();

        return $this->apply($request, $solution, $topics, route('solutions.view', ['id' => $solution->id]));
    }

    private function apply(Request $request, Questions|Solutions $item, TopicService $topics, string $back): RedirectResponse
    {
        $data = $request->validate(['topics' => ['nullable', 'array'], 'topics.*' => ['integer'], 'note' => ['nullable', 'string', 'max:1000']]);

        $changed = $topics->correct($item, $request->user(), $data['topics'] ?? [], $data['note'] ?? null);

        return redirect()->to($back.'#topics')->with('topics_notice', $changed ? 'Topics updated.' : 'Those were already the topics.');
    }
}
