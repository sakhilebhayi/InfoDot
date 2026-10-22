<?php

namespace App\Livewire\Moderation;

use App\Models\Questions;
use App\Models\Solutions;
use App\Models\Topic;
use App\Services\Knowledge\ModerationService;
use App\Services\Knowledge\TopicService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The moderators' topic desk: the list (add, rename, switch off or on) and a "needs a topic" view for the questions
 * and solutions that were written before topics existed. The page only asks; TopicService decides and writes the trail.
 */
class Topics extends Component
{
    use WithPagination;

    private const LETTERS = ['q' => 'question', 's' => 'solution'];

    #[Url(as: 'view')]
    public string $tab = 'list';

    #[Url]
    public string $kind = 'question';

    public string $newName = '';

    public string $newDescription = '';

    /** @var array<int, string> typed names and descriptions, by topic id */
    public array $names = [];

    public array $descriptions = [];

    /** @var array<string, list<string>> chosen topic ids, by item key such as q-12 */
    public array $picks = [];

    /** Runs on the first load and on every later click, so a moderator who is demoted loses the desk at once. */
    public function boot(): void
    {
        abort_unless(Auth::user()?->isModerator(), 403, 'Only moderators can open this page.');
    }

    /** Each row's form starts filled with what the topic says now, so saving without a change changes nothing. */
    public function mount(): void
    {
        foreach (Topic::all() as $topic) {
            $this->remember($topic);
        }
    }

    private function remember(Topic $topic): void
    {
        $this->names[$topic->id] = $topic->name;
        $this->descriptions[$topic->id] = (string) $topic->description;
    }

    public function updatedKind(): void
    {
        $this->resetPage();
    }

    public function add(TopicService $topics): void
    {
        $this->attempt('newName', function () use ($topics) {
            $topic = $topics->create(Auth::user(), $this->newName, $this->newDescription);
            $this->newName = $this->newDescription = '';
            $this->remember($topic);

            return '“'.$topic->name.'” is added. People can choose it from now on.';
        });
    }

    public function save(int $id, TopicService $topics): void
    {
        $topic = Topic::findOrFail($id);

        $this->attempt("names.$id", function () use ($topics, $topic, $id) {
            $changed = $topics->rename($topic, Auth::user(), $this->names[$id] ?? $topic->name, $this->descriptions[$id] ?? $topic->description);
            $this->remember($topic->refresh());

            return $changed ? 'Saved. The topic keeps its address, so links to it still work.' : 'Nothing changed.';
        });
    }

    public function toggle(int $id, TopicService $topics): void
    {
        $topic = Topic::findOrFail($id);
        $turnOn = ! $topic->active;

        $this->attempt("toggle.$id", function () use ($topics, $topic, $turnOn) {
            $topics->setActive($topic, Auth::user(), $turnOn);

            return $turnOn
                ? '“'.$topic->name.'” is on again. People can choose it.'
                : '“'.$topic->name.'” is switched off. What is filed under it stays; nobody can choose it for new questions.';
        });
    }

    /** File an untagged question or solution under the topics the moderator ticked. */
    public function assign(string $key, TopicService $topics, ModerationService $moderation): void
    {
        [$letter, $id] = array_pad(explode('-', $key, 2), 2, '');
        abort_unless(isset(self::LETTERS[$letter]) && ctype_digit($id), 404);
        $item = $moderation->find(self::LETTERS[$letter], (int) $id);

        $this->attempt("picks.$key", function () use ($topics, $item, $key) {
            $picked = $this->picks[$key] ?? [];
            $changed = $topics->correct($item, Auth::user(), is_array($picked) ? $picked : []);
            unset($this->picks[$key]);

            return $changed ? 'Filed. It is no longer on this list.' : 'It already had those topics.';
        });
    }

    /** Run an action; show its message, or the rule it broke next to the field it concerns. */
    private function attempt(string $field, callable $action): void
    {
        try {
            session()->flash('topics-status', $action());
            $this->resetErrorBag();
        } catch (ValidationException $e) {
            session()->forget('topics-status');
            $this->resetErrorBag();
            $this->addError($field, collect($e->errors())->flatten()->first());
        }
    }

    public function render(): View
    {
        $me = Auth::user();
        $tab = $this->tab === 'untagged' ? 'untagged' : 'list';
        $kind = $this->kind === 'solution' ? 'solution' : 'question';

        // What this moderator can act on: the usual visibility rules apply, and never their own content.
        $untaggedQuestions = Questions::untagged()->where('questions.user_id', '!=', $me->id);
        $untaggedSolutions = Solutions::untagged()->where('solutions.user_id', '!=', $me->id);

        $usage = [
            'questions' => DB::table('question_topic')->selectRaw('topic_id, count(*) as n')->groupBy('topic_id')->pluck('n', 'topic_id'),
            'solutions' => DB::table('solution_topic')->selectRaw('topic_id, count(*) as n')->groupBy('topic_id')->pluck('n', 'topic_id'),
        ];

        $rows = $tab === 'untagged'
            ? ($kind === 'solution'
                ? $untaggedSolutions->with('user')->latest('solutions.created_at')->latest('solutions.id')->paginate(10)
                : $untaggedQuestions->with('user')->latest('questions.created_at')->latest('questions.id')->paginate(10))
            : null;

        // A checkbox group is only an array to the browser when the property already is one: without this the first tick
        // would be sent as a single true/false.
        foreach ($rows ?? [] as $item) {
            $this->picks[($kind === 'solution' ? 's' : 'q').'-'.$item->getKey()] ??= [];
        }

        return view('livewire.moderation.topics', [
            'tab' => $tab,
            'kind' => $kind,
            'topics' => $tab === 'list' ? Topic::ordered()->get() : collect(),
            'usage' => $usage,
            'choices' => $tab === 'untagged' ? app(TopicService::class)->choices() : collect(),
            'rows' => $rows,
            'untaggedQuestionCount' => (clone $untaggedQuestions)->count(),
            'untaggedSolutionCount' => (clone $untaggedSolutions)->count(),
            'max' => Topic::MAX_PER_ITEM,
        ]);
    }
}
