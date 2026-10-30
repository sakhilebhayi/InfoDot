<?php

namespace App\Http\Controllers\Questions;

use App\Events\Knowledge\QuestionSubmitted;
use App\Events\Questions\QuestionWasAsked;
use App\Http\Controllers\Controller;
use App\Models\Answer;
use App\Models\Questions;
use App\Models\Topic;
use App\Services\Knowledge\TopicService;
use App\Support\Industry;
use App\Support\Related;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuestionsController extends Controller
{
    public function index(Request $request, TopicService $topics)
    {
        // ?topic=slug narrows the list; ?topic=none shows what has no topic yet. An unknown slug is simply an empty list.
        $slug = trim((string) $request->query('topic', ''));
        $untagged = $slug === 'none';
        $topic = $slug !== '' && ! $untagged ? Topic::where('slug', $slug)->first() : null;

        $questions = Questions::withCount(['likes', 'comments', 'answers' => fn ($q) => $q->where('status', Answer::PUBLISHED)])
            ->with(['user', 'team', 'topics'])
            ->when($untagged, fn ($q) => $q->untagged())
            ->when($slug !== '' && ! $untagged, fn ($q) => $topic ? $q->inTopic($topic) : $q->whereRaw('0 = 1'))
            ->latest()
            ->get();

        return view('questions.index', [
            'questions' => $questions,
            'industry' => Industry::for(auth()->user()->currentTeam),
            'topicChoices' => $topics->choices(),
            'activeTopic' => $topic,
            'filterSlug' => $slug,
        ]);
    }

    public function seek(Request $request, TopicService $topics)
    {
        $choices = $topics->choices();
        $preselected = $choices->firstWhere('slug', (string) $request->query('topic'));

        return view('questions.seek', [
            'industry' => Industry::for(auth()->user()->currentTeam),
            'topicChoices' => $choices,
            'preselectedTopic' => $preselected?->id,
        ]);
    }

    public function view($qid, TopicService $topics)
    {
        // Not found, not forbidden: do not reveal that another company's question exists.
        $question = Questions::where('id', $qid)->with('topics')->firstOrFail();

        return view('questions.view', [
            'question' => $question,
            'related' => Related::questions($question),
            // Only a moderator who may correct this one is given the list to pick from.
            'topicChoices' => $topics->canCorrect($question, auth()->user()) ? $topics->choicesFor($question) : null,
        ]);
    }

    public function add_question(Request $request, TopicService $topics)
    {
        $request->validate(
            [ // 1st array is field rules
                'question' => 'required|min:3|max:255',
                'description' => 'required|min:3',
                'publish' => 'nullable|boolean',
            ] + TopicService::rules(),
            [ // 2nd array is the rules custom message
                'required' => 'The :attribute field is mandatory.',
            ] + TopicService::messages());

        $question = new Questions;

        $question->user_id = auth()->user()->id;
        $question->team_id = auth()->user()->current_team_id;
        $question->visibility = $request->boolean('publish') ? Questions::VISIBILITY_PUBLIC : Questions::VISIBILITY_COMPANY;
        $question->question = $request->input('question');
        $question->description = $request->input('description');

        DB::transaction(function () use ($question, $request, $topics) {
            $question->save();
            $topics->file($question, $request->input('topics'));
        });

        event(new QuestionSubmitted('infodot:question:'.$question->id, [
            'question_id' => $question->id,
            'author_uuid' => auth()->user()->uuid,
            'tenant_team_id' => $question->team_id,
            'visibility' => $question->visibility,
        ]));

        // Nothing but a short message goes into the session. This used to flash `Questions::all()`: the session payload column is
        // TEXT (about 64 KB), so once enough long questions existed, saving the session failed after the question was already stored.
        return redirect('questions')->with('status', 'Your question was posted.');
    }
}
