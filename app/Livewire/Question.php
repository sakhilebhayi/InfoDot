<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Question extends Component
{
    public mixed $model;

    public mixed $question = null;

    public function storeLike(): void
    {
        $like = $this->model->likes()->where('user_id', Auth::id())->first();

        if ($like) {
            $like->delete();
        } else {
            $like = $this->model->likes()->make();
            $like->user()->associate(auth()->user());
            $like->save();
        }
    }

    public function markedAsSolved(): void
    {
        $this->model->update(['status' => 1]);
    }

    public function render(): View
    {
        return view('livewire.question');
    }
}
