<?php

namespace App\Livewire;

use App\Models\Questions;
use App\Models\Resource;
use App\Models\Solutions;
use App\Support\EcosystemHandoff;
use App\Support\Industry;
use App\Support\PlatformSearch;
use App\Support\Reference;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Header search. With nothing typed it suggests what to ask and what is new for this kind
 * of business; once something is typed it completes from the company's own questions and
 * solutions, jumps straight to a reference, and always offers to ask the text as a question.
 */
class Search extends Component
{
    public string $query = '';

    public int $highlightIndex = 0;

    /** Matches in Files and Documents, loaded a moment after the instant ones so typing never waits. */
    public array $external = [];

    public string $externalFor = '';

    public function updatedQuery(): void
    {
        $this->highlightIndex = 0;
        $this->external = [];
        $this->externalFor = '';
    }

    /** Ask the platforms that hold this company's files and documents. Called once per query. */
    public function loadExternal(): void
    {
        $query = trim($this->query);

        if ($this->externalFor === $query || Auth::user() === null) {
            return;
        }

        $this->externalFor = $query;

        // Each new query asks several other platforms over the network. A person typing cannot ask more than 40 times a minute.
        $key = 'search-external:'.Auth::id();

        if (RateLimiter::tooManyAttempts($key, 40)) {
            $this->external = [];

            return;
        }

        RateLimiter::hit($key, 60);
        $this->external = PlatformSearch::search(Auth::user(), Auth::user()->currentTeam, $query);
    }

    /** The examples that fit this company's kind of business. */
    #[Computed]
    public function industry(): array
    {
        return Industry::for(Auth::user()?->currentTeam);
    }

    /** @return list<string> the questions suggested while the box is empty */
    #[Computed]
    public function starters(): array
    {
        return array_slice($this->industry['questions'], 0, 4);
    }

    /** The newest things the person can see, for the empty state. */
    #[Computed]
    public function recent(): array
    {
        $questions = Questions::with('team')->latest()->limit(3)->get()->map(fn ($q) => [
            'label' => $q->question, 'url' => route('questions.view', ['qid' => $q->id]), 'ref' => Reference::for($q), 'at' => $q->created_at,
        ]);
        $solutions = Solutions::with('team')->latest()->limit(3)->get()->map(fn ($s) => [
            'label' => $s->solution_title, 'url' => route('solutions.view', ['id' => $s->id]), 'ref' => Reference::for($s), 'at' => $s->created_at,
        ]);

        return $questions->concat($solutions)->sortByDesc('at')->take(3)->values()->all();
    }

    /** A typed reference (NWFN-Q-000014) jumps straight to its item, if the person may see it. */
    #[Computed]
    public function reference(): ?Model
    {
        return blank($this->query) ? null : Reference::resolve($this->query);
    }

    /** Opens Dot.Files or Dot.docs signed in from the quick actions. */
    public function openPlatform(string $key): void
    {
        if (! in_array($key, ['files', 'docs'], true) || ! Auth::check()) {
            return;
        }

        $url = EcosystemHandoff::launchUrl(Auth::user(), $key);

        if ($url !== null) {
            $this->redirect($url);
        }
    }

    #[Computed]
    public function solutions(): Collection
    {
        if (mb_strlen(trim($this->query)) < 2) {
            return Solutions::query()->whereRaw('0=1')->get();
        }

        $like = '%'.$this->escapeLike(trim($this->query)).'%';

        // Typing the same thing again (or the same word by two people's keystrokes) is answered from memory for a minute, per person.
        return Cache::remember('search:s:u'.Auth::id().':t'.(Auth::user()->current_team_id ?? 0).':'.md5($like), 60, fn () => Solutions::where(function ($q) use ($like) {
            $q->where('solution_title', 'like', $like)
                ->orWhere('solution_description', 'like', $like)
                ->orWhere('tags', 'like', $like);
        })->latest()->limit(4)->get());
    }

    #[Computed]
    public function questions(): Collection
    {
        if (mb_strlen(trim($this->query)) < 2) {
            return Questions::query()->whereRaw('0=1')->get();
        }

        $like = '%'.$this->escapeLike(trim($this->query)).'%';

        return Cache::remember('search:q:u'.Auth::id().':t'.(Auth::user()->current_team_id ?? 0).':'.md5($like), 60, fn () => Questions::where(function ($q) use ($like) {
            $q->where('question', 'like', $like)
                ->orWhere('description', 'like', $like);
        })->latest()->limit(4)->get());
    }

    /**
     * Everything the arrow keys and Enter can land on, in the order it is shown.
     *
     * @return list<array{kind: string, label: string, url: string, ref?: string, icon: string, platform?: string}>
     */
    #[Computed]
    public function items(): array
    {
        $query = trim($this->query);

        if ($query === '') {
            return [];
        }

        $items = [];

        if ($this->reference) {
            $items[] = [
                'kind' => 'reference',
                'label' => $this->reference instanceof Resource
                    ? 'Open '.$this->reference->type.' in '.config('ecosystem.platforms.'.$this->reference->platform.'.name', $this->reference->platform)
                    : (string) ($this->reference->question ?? $this->reference->solution_title),
                'url' => Reference::route($this->reference),
                'ref' => Reference::for($this->reference),
                'icon' => 'tag',
            ];
        }

        foreach ($this->solutions as $solution) {
            $items[] = ['kind' => 'solution', 'label' => $solution->solution_title, 'url' => route('solutions.view', ['id' => $solution->id]), 'icon' => 'checklist'];
        }

        foreach ($this->questions as $question) {
            $items[] = ['kind' => 'question', 'label' => $question->question, 'url' => route('questions.view', ['qid' => $question->id]), 'icon' => 'forum'];
        }

        foreach ($this->external as $row) {
            $items[] = ['kind' => 'external', 'label' => $row['label'], 'url' => $row['url'], 'icon' => $row['icon'], 'platform' => $row['platform']];
        }

        if (mb_strlen($query) >= 3) {
            $items[] = ['kind' => 'ask', 'label' => 'Ask “'.Str::limit($query, 60).'” as a new question', 'url' => route('seek', ['question' => Str::limit($query, 255, '')]), 'icon' => 'add_comment'];
        }

        $items[] = ['kind' => 'all', 'label' => 'See every result for “'.Str::limit($query, 60).'”', 'url' => route('solution_search_results', ['search' => $query]), 'icon' => 'search'];

        return $items;
    }

    public function incrementHighlight(): void
    {
        $total = count($this->items);
        $this->highlightIndex = $total === 0 ? 0 : ($this->highlightIndex + 1) % $total;
    }

    public function decrementHighlight(): void
    {
        $total = count($this->items);
        $this->highlightIndex = $total === 0 ? 0 : ($this->highlightIndex - 1 + $total) % $total;
    }

    /** Enter opens whatever is highlighted. */
    public function openHighlighted(): void
    {
        $item = $this->items[$this->highlightIndex] ?? null;

        if ($item !== null) {
            $this->redirect($item['url']);
        }
    }

    /** The label with the typed words picked out. Escaped first, so it is safe to print raw. */
    public function mark(string $text): HtmlString
    {
        $safe = e($text);

        foreach (array_filter(preg_split('/\s+/', trim($this->query)) ?: [], fn ($w) => mb_strlen($w) >= 2) as $term) {
            $safe = preg_replace('/('.preg_quote(e($term), '/').')/iu', '<mark>$1</mark>', $safe) ?? $safe;
        }

        return new HtmlString($safe);
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }

    public function render(): View
    {
        return view('livewire.search');
    }
}
