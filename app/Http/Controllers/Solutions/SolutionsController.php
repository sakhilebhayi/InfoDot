<?php

namespace App\Http\Controllers\Solutions;

use App\Http\Controllers\Controller;
use App\Models\Solutions;
use App\Models\Steps;
use App\Models\Topic;
use App\Services\Knowledge\TopicService;
use App\Support\Industry;
use App\Support\Related;
use Illuminate\Http\Request;

class SolutionsController extends Controller
{
    public function index(Request $request, TopicService $topics)
    {
        // ?topic=slug narrows the list; ?topic=none shows what has no topic yet. An unknown slug is simply an empty list.
        $slug = trim((string) $request->query('topic', ''));
        $untagged = $slug === 'none';
        $topic = $slug !== '' && ! $untagged ? Topic::where('slug', $slug)->first() : null;

        $solutions = Solutions::withCount(['likes', 'comments'])
            ->with(['user', 'team', 'topics'])
            ->when($untagged, fn ($q) => $q->untagged())
            ->when($slug !== '' && ! $untagged, fn ($q) => $topic ? $q->inTopic($topic) : $q->whereRaw('0 = 1'))
            ->latest()
            ->get();

        return view('solutions.index', [
            'solutions' => $solutions,
            'industry' => Industry::for(auth()->user()->currentTeam),
            'topicChoices' => $topics->choices(),
            'activeTopic' => $topic,
            'filterSlug' => $slug,
        ]);
    }

    public function create(Request $request, TopicService $topics)
    {
        $choices = $topics->choices();

        return view('solutions.create', [
            'industry' => Industry::for(auth()->user()->currentTeam),
            'topicChoices' => $choices,
            'preselectedTopic' => $choices->firstWhere('slug', (string) $request->query('topic'))?->id,
        ]);
    }

    public function add_solution(Request $request, TopicService $topics)
    {
        $request->validate(
            [ // 1st array is field rules
                'solution_title' => 'required|min:3|max:255',
                'solution_description' => 'required|min:3',
                'tags-input' => 'required|min:3',
                'publish' => 'nullable|boolean',
                'duration' => 'required|integer|min:1',
                'duration_type' => 'required|in:hours,days,weeks,months,years,infinite',
                'solution_heading' => 'required|array|min:1',
                'solution_heading.*' => 'required|string|max:255',
                'solution_body' => 'required|array',
                'solution_body.*' => 'required|string',
            ] + TopicService::rules(),
            [ // 2nd array is the rules custom message
                'required' => 'The :attribute field is mandatory.',
            ] + TopicService::messages());

        $solution = new Solutions;

        $solution->user_id = auth()->user()->id;
        $solution->team_id = auth()->user()->current_team_id;
        $solution->visibility = $request->boolean('publish') ? Solutions::VISIBILITY_PUBLIC : Solutions::VISIBILITY_COMPANY;
        $solution->solution_title = $request->input('solution_title');
        $solution->solution_description = $request->input('solution_description');
        $solution->tags = $request->input('tags-input');
        $solution->duration = $request->input('duration');
        $solution->duration_type = $request->input('duration_type');
        $solution->steps = count($request->input('solution_heading'));
        $solution->save();
        $topics->file($solution, $request->input('topics'));

        $steps = $request->input('solution_heading');

        foreach ($steps as $key => $n) {
            $solution_step = new Steps;

            $solution_step->user_id = auth()->user()->id;
            $solution_step->solution_id = $solution->id;
            $solution_step->solution_heading = $request->input('solution_heading')[$key];
            $solution_step->solution_body = $request->input('solution_body')[$key];
            $solution_step->save();
        }

        return redirect()->route('solutions');
    }

    public function view_solution($id, TopicService $topics)
    {
        $solution = Solutions::where('id', $id)->with('topics')->firstOrFail();

        return view('solutions.view', [
            'solution' => $solution,
            'steps' => Steps::where('solution_id', $solution->id)->get(),
            'related' => Related::solutions($solution),
            // Only a moderator who may correct this one is given the list to pick from.
            'topicChoices' => $topics->canCorrect($solution, auth()->user()) ? $topics->choicesFor($solution) : null,
        ]);
    }
}
