<?php

namespace App\Livewire\Moderation;

use App\Models\Answer;
use App\Models\LibraryEntry;
use App\Models\LibraryOutcome;
use App\Models\Solutions;
use App\Models\User;
use App\Services\Library\LibraryOutcomeOutbox;
use App\Services\Library\LibraryService;
use App\Services\Library\MemoryRecommendationsClient;
use App\Services\Library\RecommendationsUnavailableException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * The moderators' Verified library desk. "Recommended" lists what Dot.Brain suggests (read from Dot.Memory, never
 * invented: when Memory cannot be reached the page says so) next to the real content, so a moderator reads the item and
 * Brain's plain-language reasons, then adopts or declines. Below are the entries already in the library, which a
 * moderator can withdraw. The page only asks; LibraryService decides, re-checks everything and writes the audit trail.
 */
class Library extends Component
{
    use WithPagination;

    /** Where in Memory's list this page starts; empty is the first page. */
    #[Url(as: 'after')]
    public string $cursor = '';

    /** @var array<string, string> typed reasons by entry id */
    public array $withdrawReasons = [];

    /**
     * Which contribution each recommendation on screen is about, as Memory gave it. Locked: a browser cannot change it,
     * so a click on one recommendation can never decide another item.
     *
     * @var array<string, string>
     */
    #[Locked]
    public array $refs = [];

    /** Runs on the first load and on every later click, so a moderator who is demoted loses the desk at once. */
    public function boot(): void
    {
        abort_unless(Auth::user()?->isModerator(), 403, 'Only moderators can open this page.');
    }

    public function adopt(string $decisionId, LibraryService $library): void
    {
        if (($ref = $this->refFor($decisionId)) === null) {
            return;
        }

        $item = $library->find($ref);
        abort_if($item === null, 404);

        $this->attempt("adopt.$decisionId", function () use ($library, $item, $decisionId) {
            ['added' => $added] = $library->adopt($item, Auth::user(), $decisionId);

            return $added
                ? 'Adopted into the library. The author\'s reward can now be paid, and Dot.Memory will be told.'
                : 'It was already in the library. This recommendation is now closed.';
        });
    }

    public function decline(string $decisionId, LibraryService $library): void
    {
        if (($ref = $this->refFor($decisionId)) === null) {
            return;
        }

        $this->attempt("decline.$decisionId", function () use ($library, $ref, $decisionId) {
            return $library->decline($decisionId, $ref, Auth::user())
                ? 'Declined. Dot.Memory will be told.'
                : 'This recommendation was already decided.';
        });
    }

    public function withdraw(int $entryId, LibraryService $library): void
    {
        $entry = LibraryEntry::findOrFail($entryId);

        $this->attempt("withdrawReasons.$entryId", function () use ($library, $entry, $entryId) {
            $done = $library->withdraw($entry, Auth::user(), $this->withdrawReasons[$entryId] ?? '');
            unset($this->withdrawReasons[$entryId]);

            return $done ? 'Withdrawn. It no longer shows in the library, and the points it earned are taken back.' : 'It was already withdrawn.';
        });
    }

    public function nextRecommendations(string $cursor): void
    {
        $this->cursor = $cursor;
    }

    public function firstRecommendations(): void
    {
        $this->cursor = '';
    }

    /**
     * The contribution a recommendation on screen is about. A recommendation that is not on screen is unknown (404),
     * except one already decided here a moment ago (a double click): that is said plainly and nothing happens.
     */
    private function refFor(string $decisionId): ?string
    {
        $ref = $this->refs[$decisionId] ?? null;

        if ($ref !== null) {
            return $ref;
        }

        abort_unless(LibraryOutcome::where('decision_event_id', $decisionId)->exists(), 404);
        session()->flash('moderation-status', 'This recommendation was already decided.');

        return null;
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

    public function render(MemoryRecommendationsClient $client, LibraryService $library, LibraryOutcomeOutbox $outbox): View
    {
        $me = Auth::user();
        $recommendations = [];
        $unavailable = null;
        $next = null;
        $skipped = 0;
        $this->refs = [];

        try {
            $page = $client->recommendations('open', 50, $this->cursor !== '' ? $this->cursor : null);
            $next = $page['next_cursor'];
            $skipped = $page['skipped'];

            // A decision made here that Memory has not been told about yet is already decided: do not offer it again.
            $decided = LibraryOutcome::whereIn('decision_event_id', array_column($page['items'], 'decision_event_id'))->pluck('outcome', 'decision_event_id');

            foreach ($page['items'] as $rec) {
                if ($decided->has($rec['decision_event_id'])) {
                    continue;
                }
                $this->refs[$rec['decision_event_id']] = $rec['contribution_ref'];
                $recommendations[] = $rec + ['content' => $this->describe($library->find($rec['contribution_ref']), $me, $library)];
            }
        } catch (RecommendationsUnavailableException $e) {
            $unavailable = $e->reason;
        }

        $entries = LibraryEntry::active()->with(['answer.question.topics', 'solution.topics'])
            ->orderByDesc('adopted_at')->orderByDesc('id')->paginate(10, pageName: 'entries');
        $entries->getCollection()->transform(fn (LibraryEntry $entry) => [
            'entry' => $entry, 'content' => $this->describe($entry->source(), $me, $library), 'shown' => $this->isLive($entry->source()),
        ]);

        return view('livewire.moderation.library', [
            'recommendations' => $recommendations,
            'unavailable' => $unavailable,
            'next' => $next,
            'skipped' => $skipped,
            'entries' => $entries,
            'waiting' => $outbox->waiting(),
        ]);
    }

    /** Whether the library page would list this source right now (public, published, not hidden). */
    private function isLive(?Model $source): bool
    {
        return match (true) {
            $source instanceof Answer => ! $source->trashed() && $source->status === Answer::PUBLISHED && ! $source->isHidden()
                && $source->question !== null && ! $source->question->isHidden() && $source->question->isPublic(),
            $source instanceof Solutions => ! $source->isHidden() && $source->isPublic(),
            default => false,
        };
    }

    /**
     * What the row shows about the item. Company-only content is never shown, whoever is looking; everything else a
     * moderator is shown comes straight from the source now.
     *
     * @return array<string, mixed>
     */
    private function describe(?Model $item, User $me, LibraryService $library): array
    {
        if ($item === null) {
            return ['gone' => true];
        }

        $isAnswer = $item instanceof Answer;
        $home = $isAnswer ? $item->question : $item;
        $public = $home !== null && $home->isPublic();

        $base = [
            'gone' => false, 'public' => $public, 'kind' => $isAnswer ? 'answer' : 'solution', 'ref' => LibraryService::refOf($item),
            'own' => $item->user_id === $me->id, 'problem' => $library->problem($item),
            'hidden' => $item->isHidden() || ($isAnswer && $item->trashed()) || ($isAnswer && $home?->isHidden()),
        ];

        if (! $public) {
            return $base;
        }

        $topics = ($isAnswer ? $home?->topics : $item->topics)?->sortBy(['sort_order', 'id'])->pluck('name')->all() ?? [];
        $corroboration = $library->corroboration($item);

        return $base + [
            'title' => $isAnswer ? 'Answer to: '.Str::limit((string) $home?->question, 140) : Str::limit((string) $item->solution_title, 160),
            'excerpt' => Str::limit((string) ($isAnswer ? $item->body : $item->solution_description), 260),
            'url' => $isAnswer ? route('questions.view', ['qid' => $item->question_id]).'#answer-'.$item->id : route('solutions.view', ['id' => $item->id]),
            'author' => $item->user,
            'topics' => $topics,
            'corroboration' => $corroboration,
        ];
    }
}
