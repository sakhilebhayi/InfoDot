<?php

namespace App\Livewire;

use App\Models\Solutions;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

class SolutionList extends Component
{
    public int $perPage = 2;

    public int $page = 1;

    public Collection $solutionsCollection;

    public function mount(): void
    {
        $this->solutionsCollection = Solutions::latest()->take($this->perPage)->get();
    }

    public function loadMore(): void
    {
        $solutions = Solutions::latest()
            ->take($this->perPage)
            ->skip((($this->page - 1) * $this->perPage) + $this->perPage)
            ->get();

        $this->solutionsCollection->push(...$solutions);
        $this->page++;
    }

    public function render(): View
    {
        $solutions = new LengthAwarePaginator(
            $this->solutionsCollection,
            Solutions::count(),
            $this->perPage,
            $this->page
        );

        return view('livewire.solution-list', ['solutions' => $solutions]);
    }
}
