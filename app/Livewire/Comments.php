<?php

namespace App\Livewire;

use App\Models\Solutions;
use App\Models\User;
use App\Models\UserNotice;
use App\Services\Knowledge\AnswerService;
use App\Services\Knowledge\InAppNotices;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Livewire\Component;

class Comments extends Component
{
    public mixed $model;

    public mixed $question = null;

    public mixed $solution = null;

    public array $newCommentState = ['body' => ''];

    protected array $rules = [
        'newCommentState.body' => 'required|max:5000',
    ];

    protected array $validationAttributes = [
        'newCommentState.body' => 'comment',
    ];

    /** Livewire restores the model without the visibility scopes, so every call asks again whether this person may still see it. */
    private function ensureVisible(): void
    {
        abort_unless(auth()->check() && app(AnswerService::class)->canSeeContent($this->model, auth()->user()), 404);
    }

    public function storeLike(): void
    {
        $this->ensureVisible();

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
        // The button is only drawn for the asker, but a Livewire method can be
        // called by anyone signed in, so the owner check lives here too.
        abort_unless(Auth::id() === $this->model->user_id, 403);

        $this->model->update(['status' => 1]);

        // The view reads $question, a separate copy of the same record; point it at
        // the updated one so the page shows the new state (and the stamp) at once.
        if ($this->question !== null) {
            $this->question = $this->model;
        }
    }

    public function postComment(): void
    {
        $this->ensureVisible();
        $this->validate();

        // A person cannot flood a thread (each comment also notifies the owner).
        $limit = 'comment:'.auth()->id();

        if (RateLimiter::tooManyAttempts($limit, 20)) {
            $this->addError('newCommentState.body', 'You are posting too fast. Please wait a minute.');

            return;
        }

        RateLimiter::hit($limit, 60);

        $comment = $this->model->comments()->make(['body' => $this->newCommentState['body']]);
        $comment->user()->associate(auth()->user());
        $comment->save();

        // The owner of the question or solution is told someone commented (often a request for more detail). Never the commenter,
        // and never able to fail the comment itself.
        try {
            $owner = $this->model->user_id ? User::find($this->model->user_id) : null;
            if ($owner !== null) {
                app(InAppNotices::class)->record(
                    UserNotice::COMMENT_RECEIVED, $owner,
                    $this->model instanceof Solutions ? 'solution' : 'question', (int) $this->model->getKey(),
                    'infodot:comment:'.$comment->getKey(), auth()->user(),
                );
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $this->newCommentState = ['body' => ''];
    }

    public function render(): View
    {
        $this->ensureVisible();

        $comments = $this->model
            ->comments()
            ->with('user', 'children.user', 'children.children')
            ->parent()
            ->latest()
            ->get();

        return view('livewire.comments', ['comments' => $comments]);
    }
}
