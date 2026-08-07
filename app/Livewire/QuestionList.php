<?php

namespace App\Livewire;

use App\Models\Questions;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;

class QuestionList extends Component
{
    public int $perPage = 2;

    public int $page = 1;

    public Collection $questionsCollection;

    public function mount(): void
    {
        $this->questionsCollection = Questions::latest()->take($this->perPage)->get();
    }

    public function loadMore(): void
    {
        $questions = Questions::latest()
            ->take($this->perPage)
            ->skip((($this->page - 1) * $this->perPage) + $this->perPage)
            ->get();

        $this->questionsCollection->push(...$questions);
        $this->page++;
    }

    public function render(): View
    {
        $questions = new LengthAwarePaginator(
            $this->questionsCollection,
            Questions::count(),
            $this->perPage,
            $this->page
        );

        return view('livewire.question-list', ['questions' => $questions]);
    }
}
