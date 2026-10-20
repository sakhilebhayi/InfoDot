<?php

namespace App\Livewire\Moderation;

use App\Models\Answer;
use App\Models\ContentReport;
use App\Models\ModerationAppeal;
use App\Models\Questions;
use App\Models\Solutions;
use App\Models\User;
use App\Services\Knowledge\AnswerService;
use App\Services\Knowledge\ModerationService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The moderators' desk: reported items (one row per item, however many people reported it) and appeals against hides.
 * The page only asks; ModerationService decides who may do what and writes the audit trail.
 */
class Queue extends Component
{
    use WithPagination;

    private const LETTERS = ['q' => 'question', 'a' => 'answer', 's' => 'solution'];

    #[Url(as: 'tab')]
    public string $tab = 'reports';

    #[Url]
    public string $status = 'open';

    #[Url]
    public string $reason = '';

    /** @var array<string, string> typed text, by item key such as q-12 or by appeal id */
    public array $dismissNotes = [];

    public array $hideReasons = [];

    public array $restoreNotes = [];

    public array $appealNotes = [];

    /** Runs on the first load and on every later click, so a moderator who is demoted loses the desk at once. */
    public function boot(): void
    {
        abort_unless(Auth::user()?->isModerator(), 403, 'Only moderators can open this page.');
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedReason(): void
    {
        $this->resetPage();
    }

    public function dismiss(string $key, ModerationService $moderation): void
    {
        $item = $this->item($key);

        $this->attempt("dismissNotes.$key", function () use ($moderation, $item, $key) {
            $count = $moderation->dismiss($item, Auth::user(), $this->dismissNotes[$key] ?? '');
            unset($this->dismissNotes[$key]);

            return $count === 0 ? 'There was nothing open to dismiss: someone else has already dealt with it.' : 'Dismissed. The reports are closed and the item stays as it is.';
        });
    }

    public function hide(string $key, ModerationService $moderation): void
    {
        $item = $this->item($key);

        $this->attempt("hideReasons.$key", function () use ($moderation, $item, $key) {
            $done = $moderation->hide($item, Auth::user(), $this->hideReasons[$key] ?? '');
            unset($this->hideReasons[$key]);

            return $done ? 'Hidden. Its author can see why and can appeal.' : 'It was already hidden.';
        });
    }

    public function restore(string $key, ModerationService $moderation): void
    {
        $item = $this->item($key);

        $this->attempt("restoreNotes.$key", function () use ($moderation, $item, $key) {
            $done = $moderation->restore($item, Auth::user(), $this->restoreNotes[$key] ?? '');
            unset($this->restoreNotes[$key]);

            return $done ? 'Restored. Everyone who could see it before can see it again.' : 'It was not hidden.';
        });
    }

    public function uphold(int $appealId, ModerationService $moderation): void
    {
        $this->decide($appealId, true, $moderation);
    }

    public function deny(int $appealId, ModerationService $moderation): void
    {
        $this->decide($appealId, false, $moderation);
    }

    private function decide(int $appealId, bool $uphold, ModerationService $moderation): void
    {
        $appeal = ModerationAppeal::findOrFail($appealId);

        $this->attempt("appealNotes.$appealId", function () use ($moderation, $appeal, $uphold, $appealId) {
            $moderation->decideAppeal($appeal, Auth::user(), $uphold, $this->appealNotes[$appealId] ?? '');
            unset($this->appealNotes[$appealId]);

            return $uphold ? 'Appeal upheld. The content is visible again.' : 'Appeal denied. The author can see your note.';
        });
    }

    /** Run an action; show its message, or the rule it broke next to the field it concerns. */
    private function attempt(string $field, callable $action): void
    {
        try {
            session()->flash('moderation-status', $action());
            $this->resetErrorBag();
        } catch (ValidationException $e) {
            session()->forget('moderation-status');
            $this->resetErrorBag();
            $this->addError($field, collect($e->errors())->flatten()->first());
        }
    }

    private function item(string $key): Model
    {
        [$letter, $id] = array_pad(explode('-', $key, 2), 2, '');
        abort_unless(isset(self::LETTERS[$letter]) && ctype_digit($id), 404);

        return app(ModerationService::class)->find(self::LETTERS[$letter], (int) $id);
    }

    public static function keyFor(Model $item): string
    {
        return array_search(ModerationService::kindOf($item), self::LETTERS, true).'-'.$item->getKey();
    }

    public function render(ModerationService $moderation, AnswerService $answers): View
    {
        $me = Auth::user();
        $tab = $this->tab === 'appeals' ? 'appeals' : 'reports';
        $statuses = $tab === 'appeals' ? [ModerationAppeal::OPEN, ModerationAppeal::UPHELD, ModerationAppeal::DENIED] : ContentReport::STATUSES;
        $status = in_array($this->status, $statuses, true) ? $this->status : ModerationAppeal::OPEN;
        $reason = in_array($this->reason, ContentReport::REASONS, true) && $tab === 'reports' ? $this->reason : '';

        $rows = $tab === 'appeals'
            ? $this->appealRows($status, $me, $moderation, $answers)
            : $this->reportRows($status, $reason, $me, $moderation, $answers);

        return view('livewire.moderation.queue', [
            'tab' => $tab,
            'status' => $status,
            'reason' => $reason,
            'statuses' => $statuses,
            'rows' => $rows,
            'openReports' => DB::table('content_reports')->where('status', ContentReport::OPEN)->select('reportable_type', 'reportable_id')->groupBy('reportable_type', 'reportable_id')->get()->count(),
            'openAppeals' => ModerationAppeal::where('status', ModerationAppeal::OPEN)->count(),
            'moderation' => $moderation,
        ]);
    }

    private function reportRows(string $status, string $reason, User $me, ModerationService $moderation, AnswerService $answers)
    {
        $page = DB::table('content_reports')
            ->where('status', $status)
            ->when($reason !== '', fn ($q) => $q->whereExists(fn ($sub) => $sub->selectRaw('1')->from('content_reports as same_item')
                ->whereColumn('same_item.reportable_type', 'content_reports.reportable_type')
                ->whereColumn('same_item.reportable_id', 'content_reports.reportable_id')
                ->where('same_item.status', $status)->where('same_item.reason', $reason)))
            ->groupBy('reportable_type', 'reportable_id')
            ->selectRaw('reportable_type, reportable_id, count(*) as reports, min(created_at) as first_at, max(resolved_at) as last_resolved_at')
            ->when($status === ContentReport::OPEN, fn ($q) => $q->orderBy('first_at'), fn ($q) => $q->orderByDesc('last_resolved_at'))
            ->orderBy('reportable_id')
            ->paginate(10);

        $page->getCollection()->transform(function ($group) use ($status, $me, $moderation, $answers) {
            $item = $moderation->resolve($group->reportable_type, (int) $group->reportable_id);
            $reports = ContentReport::with(['reporter', 'resolver'])
                ->where('reportable_type', $group->reportable_type)->where('reportable_id', $group->reportable_id)
                ->where('status', $status)->orderBy('id')->get();

            return [
                'item' => $this->describe($item, $me, $moderation, $answers),
                'count' => (int) $group->reports,
                'since' => Carbon::parse($group->first_at),
                'reports' => $reports,
            ];
        });

        return $page;
    }

    private function appealRows(string $status, User $me, ModerationService $moderation, AnswerService $answers)
    {
        $page = ModerationAppeal::with(['appellant', 'hiddenBy', 'decider'])
            ->where('status', $status)
            ->when($status === ModerationAppeal::OPEN, fn ($q) => $q->orderBy('created_at'), fn ($q) => $q->orderByDesc('decided_at'))
            ->orderBy('id')
            ->paginate(10);

        $page->getCollection()->transform(fn (ModerationAppeal $appeal) => [
            'appeal' => $appeal,
            'item' => $this->describe($moderation->resolve($appeal->subject_type, $appeal->subject_id), $me, $moderation, $answers),
            'canDecide' => $moderation->canDecide($appeal, $me),
        ]);

        return $page;
    }

    /** What the row shows about the item. Items private to another company are named but never shown. */
    private function describe(?Model $item, User $me, ModerationService $moderation, AnswerService $answers): array
    {
        if ($item === null) {
            return ['gone' => true];
        }

        if (! $answers->canSeeContent($item, $me)) {
            return ['gone' => false, 'restricted' => true, 'kind' => ModerationService::kindOf($item)];
        }

        $kind = ModerationService::kindOf($item);
        $base = [
            'gone' => false, 'restricted' => false, 'model' => $item, 'kind' => $kind, 'key' => self::keyFor($item),
            'hidden' => $item->isHidden(), 'hiddenReason' => $item->hidden_reason,
            'canModerate' => $moderation->canModerate($item, $me),
            'own' => $item->user_id === $me->id,
            'history' => $moderation->history($item),
        ];

        return $base + match (true) {
            $item instanceof Answer => [
                'title' => 'Answer to: '.Str::limit((string) $item->question?->question, 120),
                'excerpt' => Str::limit((string) $item->body, 260),
                'author' => $item->user,
                'url' => route('questions.view', ['qid' => $item->question_id]).'#answer-'.$item->id,
                'accepted' => $item->isAccepted(),
            ],
            $item instanceof Questions => [
                'title' => Str::limit((string) $item->question, 160),
                'excerpt' => Str::limit((string) $item->description, 260),
                'author' => $item->user,
                'url' => route('questions.view', ['qid' => $item->id]),
            ],
            $item instanceof Solutions => [
                'title' => Str::limit((string) $item->solution_title, 160),
                'excerpt' => Str::limit((string) $item->solution_description, 260),
                'author' => $item->user,
                'url' => route('solutions.view', ['id' => $item->id]),
                'verified' => $item->isVerified(),
            ],
        };
    }
}
