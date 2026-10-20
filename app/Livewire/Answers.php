<?php

namespace App\Livewire;

use App\Models\Answer;
use App\Models\Questions;
use App\Services\Knowledge\AnswerService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * The formal answers under a question. The browser asks; AnswerService decides (who may answer, accept or
 * report), so a hand-made Livewire call cannot do more than the buttons can.
 */
class Answers extends Component
{
    public Questions $question;

    public string $body = '';

    public ?int $reporting = null;

    public string $reason = 'low_quality';

    public function mount(Questions $question): void
    {
        $this->question = $question;
    }

    public function publish(AnswerService $service): void
    {
        $this->validateOnly('body', ['body' => ['required', 'string']]);

        $service->publish($this->question, Auth::user(), $this->body);
        $this->reset('body');
        session()->flash('answer-status', 'Your answer is posted.');
    }

    public function accept(int $answerId, AnswerService $service): void
    {
        $service->accept($this->answer($answerId), Auth::user());
        $this->question->refresh();
    }

    public function revoke(int $answerId, AnswerService $service): void
    {
        $service->revokeAcceptance($this->answer($answerId), Auth::user());
        $this->question->refresh();
    }

    public function startReport(int $answerId): void
    {
        $this->reporting = $answerId;
    }

    public function report(AnswerService $service): void
    {
        abort_unless($this->reporting !== null, 422);
        $service->report($this->answer($this->reporting), Auth::user(), $this->reason);
        $this->reporting = null;
        session()->flash('answer-status', 'Thanks. A moderator will look at it.');
    }

    public function render(AnswerService $service): View
    {
        $user = Auth::user();
        $answers = Answer::where('question_id', $this->question->id)
            ->where('status', Answer::PUBLISHED)
            ->with('user')
            ->orderByRaw('accepted_at is null')->orderBy('created_at')
            ->get();

        return view('livewire.answers', [
            'answers' => $answers,
            'service' => $service,
            'user' => $user,
            'canAnswer' => $service->canSee($this->question, $user),
        ]);
    }

    private function answer(int $id): Answer
    {
        return Answer::where('question_id', $this->question->id)->findOrFail($id);
    }
}
