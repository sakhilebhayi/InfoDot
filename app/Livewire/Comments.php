<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

class Comments extends Component
{
    public mixed $model;

    public mixed $question = null;

    public mixed $solution = null;

    public array $newCommentState = ['body' => ''];

    protected array $rules = [
        'newCommentState.body' => 'required',
    ];

    protected array $validationAttributes = [
        'newCommentState.body' => 'comment',
    ];

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

    public function postComment(): void
    {
        $this->validate();

        $comment = $this->model->comments()->make(['body' => $this->newCommentState['body']]);
        $comment->user()->associate(auth()->user());
        $comment->save();

        $this->newCommentState = ['body' => ''];
    }

    public function render(): View
    {
        $comments = $this->model
            ->comments()
            ->with('user', 'children.user', 'children.children')
            ->parent()
            ->latest()
            ->get();

        return view('livewire.comments', ['comments' => $comments]);
    }
}
