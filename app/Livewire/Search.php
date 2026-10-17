<?php

namespace App\Livewire;

use App\Models\Questions;
use App\Models\Solutions;
use App\Support\EcosystemHandoff;
use App\Support\Reference;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Computed;
use Livewire\Component;

class Search extends Component
{
    public string $query = '';

    public int $highlightIndex = 0;

    public function updatedQuery(): void
    {
        $this->highlightIndex = 0;
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
        if (blank($this->query)) {
            return Solutions::query()->whereRaw('0=1')->get();
        }

        $like = '%'.$this->query.'%';

        return Solutions::where(function ($q) use ($like) {
            $q->where('solution_title', 'like', $like)
                ->orWhere('solution_description', 'like', $like)
                ->orWhere('tags', 'like', $like);
        })->latest()->limit(5)->get();
    }

    #[Computed]
    public function questions(): Collection
    {
        if (blank($this->query)) {
            return Questions::query()->whereRaw('0=1')->get();
        }

        $like = '%'.$this->query.'%';

        return Questions::where(function ($q) use ($like) {
            $q->where('question', 'like', $like)
                ->orWhere('description', 'like', $like);
        })->latest()->limit(5)->get();
    }

    public function incrementHighlight(): void
    {
        $total = $this->solutions->count() + $this->questions->count();
        $this->highlightIndex = $this->highlightIndex >= $total ? 0 : $this->highlightIndex + 1;
    }

    public function decrementHighlight(): void
    {
        $total = $this->solutions->count() + $this->questions->count();
        $this->highlightIndex = $this->highlightIndex <= 0 ? $total : $this->highlightIndex - 1;
    }

    public function render(): View
    {
        return view('livewire.search');
    }
}
