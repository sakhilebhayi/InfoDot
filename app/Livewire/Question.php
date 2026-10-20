<?php

namespace App\Livewire;

use App\Services\Knowledge\LikeToggle;
use Illuminate\View\View;
use Livewire\Component;

class Question extends Component
{
    public mixed $model;

    public mixed $question = null;

    public function storeLike(LikeToggle $likes): void
    {
        $likes->toggle($this->model, auth()->user());
    }

    /**
     * Marking a question solved by hand is the asker's call (or a moderator's). Before this check any signed-in
     * person could call it. Accepting an answer (AnswerService) is the normal way and also marks it solved.
     */
    public function markedAsSolved(): void
    {
        $user = auth()->user();

        abort_unless($this->model->user_id === $user->id || $user->isModerator(), 403);

        $this->model->update(['status' => 1]);
    }

    public function render(): View
    {
        return view('livewire.question');
    }
}
