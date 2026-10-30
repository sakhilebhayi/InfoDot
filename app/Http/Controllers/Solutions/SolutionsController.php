<?php

namespace App\Http\Controllers\Solutions;

use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\Solutions;
use App\Models\SolutionVersion;
use App\Models\Steps;
use App\Models\Topic;
use App\Models\User;
use App\Services\Attachments\AttachmentService;
use App\Services\Knowledge\TopicService;
use App\Support\Industry;
use App\Support\Related;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SolutionsController extends Controller
{
    /** The list pages show the latest this many and say so; narrowing by topic reaches the rest. Unbounded, they grew with the table. */
    private const LIST_LIMIT = 200;

    public function index(Request $request, TopicService $topics)
    {
        // ?topic=slug narrows the list; ?topic=none shows what has no topic yet. An unknown slug is simply an empty list.
        $slug = trim((string) $request->query('topic', ''));
        $untagged = $slug === 'none';
        $topic = $slug !== '' && ! $untagged ? Topic::where('slug', $slug)->first() : null;

        $has = trim((string) $request->query('has', ''));
        $hasKind = Attachment::kindFromWord($has);

        $solutions = Solutions::withCount(['likes', 'comments'])
            ->withAttachmentCounts()
            ->withCount([
                'stepAttachments as step_image_count' => fn ($q) => $q->where('kind', Attachment::IMAGE),
                'stepAttachments as step_pdf_count' => fn ($q) => $q->where('kind', Attachment::PDF),
                'stepAttachments as step_video_count' => fn ($q) => $q->where('kind', Attachment::VIDEO),
            ])
            ->with(['user', 'team', 'topics'])
            ->when($untagged, fn ($q) => $q->untagged())
            ->when($slug !== '' && ! $untagged, fn ($q) => $topic ? $q->inTopic($topic) : $q->whereRaw('0 = 1'))
            ->when($hasKind !== null, fn ($q) => $q->where(fn ($w) => $w
                ->whereHas('attachments', fn ($a) => $a->where('kind', $hasKind))
                ->orWhereHas('stepAttachments', fn ($a) => $a->where('kind', $hasKind))))
            ->latest()
            ->limit(self::LIST_LIMIT)
            ->get();

        return view('solutions.index', [
            'solutions' => $solutions,
            'limited' => $solutions->count() >= self::LIST_LIMIT,
            'industry' => Industry::for(auth()->user()->currentTeam),
            'topicChoices' => $topics->choices(),
            'activeTopic' => $topic,
            'filterSlug' => $slug,
            'filterHas' => $has,
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

    public function add_solution(Request $request, TopicService $topics, AttachmentService $attachments)
    {
        $request->validate(
            [ // 1st array is field rules
                'solution_title' => 'required|min:3|max:255',
                'solution_description' => 'required|min:3|max:20000',
                'tags-input' => 'required|min:3|max:255',
                'publish' => 'nullable|boolean',
                'duration' => 'required|integer|min:1|max:10000',
                'duration_type' => 'required|in:hours,days,weeks,months,years,infinite',
                'solution_heading' => 'required|array|min:1|max:50',
                'solution_heading.*' => 'required|string|max:255',
                'solution_body' => 'required|array|max:50',
                'solution_body.*' => 'required|string|max:5000',
                // Per-step uploads arrive keyed by the step's position in the form.
                'step_attachments' => 'nullable|array|max:50',
                'step_attachments.*' => 'nullable|array|max:'.(int) config('attachments.max_per_item'),
                'step_attachments.*.*.uuid' => 'required|uuid',
                'step_attachments.*.*.caption' => 'nullable|string|max:500',
            ] + TopicService::rules() + AttachmentService::rules(),
            [ // 2nd array is the rules custom message
                'required' => 'The :attribute field is mandatory.',
            ] + TopicService::messages());

        // The solution, its topics, every step and the uploads are saved together: a failure part-way leaves nothing half-made.
        DB::transaction(function () use ($request, $topics, $attachments) {
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
            $attachments->bind($request->input('attachments', []) ?: [], $solution, $request->user());

            foreach ($request->input('solution_heading') as $key => $n) {
                $solution_step = new Steps;

                $solution_step->user_id = auth()->user()->id;
                $solution_step->solution_id = $solution->id;
                $solution_step->solution_heading = $request->input('solution_heading')[$key];
                $solution_step->solution_body = $request->input('solution_body')[$key];
                $solution_step->save();

                $attachments->bind($request->input('step_attachments.'.$key, []) ?: [], $solution_step, $request->user());
            }
        });

        return redirect()->route('solutions');
    }

    public function view_solution($id, TopicService $topics)
    {
        $solution = Solutions::where('id', $id)->with(['topics', 'attachments'])->firstOrFail();

        // The people whose approved improvements shaped this solution (the original author keeps ownership).
        $improvers = User::whereIn('id', SolutionVersion::where('solution_id', $solution->id)
            ->whereNotNull('contributor_id')->distinct()->pluck('contributor_id'))->get(['id', 'name']);

        return view('solutions.view', [
            'solution' => $solution,
            'improvers' => $improvers,
            'steps' => Steps::where('solution_id', $solution->id)->with('attachments')->get(),
            'related' => Related::solutions($solution),
            // Only a moderator who may correct this one is given the list to pick from.
            'topicChoices' => $topics->canCorrect($solution, auth()->user()) ? $topics->choicesFor($solution) : null,
        ]);
    }
}
