<?php

namespace App\Livewire;

use App\Models\Solutions;
use App\Services\Knowledge\ModerationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Verification of a solution by a moderator who is not its author. The badge is shown to everyone; the buttons only to
 * moderators who may use them. ModerationService decides and writes the audit row.
 */
class VerifySolution extends Component
{
    #[Locked]
    public int $solutionId;

    public string $note = '';

    public function mount(int $solutionId): void
    {
        $this->solutionId = $solutionId;
    }

    private function solution(): Solutions
    {
        return Solutions::findOrFail($this->solutionId);
    }

    public function verify(ModerationService $moderation): void
    {
        $this->attempt(fn () => $moderation->verify($this->solution(), Auth::user(), $this->note));
    }

    public function unverify(ModerationService $moderation): void
    {
        $this->attempt(fn () => $moderation->unverify($this->solution(), Auth::user(), $this->note));
    }

    private function attempt(callable $action): void
    {
        try {
            $action();
            $this->reset('note');
            $this->resetErrorBag();
            // The "Verified by moderator" chip under the page title is drawn by the page, not by this component.
            $this->dispatch('solution-verification', label: $this->solution()->isVerified() ? 'Verified by moderator' : '');
        } catch (ValidationException $e) {
            $this->resetErrorBag();
            $this->addError('note', collect($e->errors())->flatten()->first());
        }
    }

    public function render(ModerationService $moderation): View
    {
        $solution = $this->solution()->load('verifier');

        return view('livewire.verify-solution', [
            'solution' => $solution,
            'canModerate' => $moderation->canModerate($solution, Auth::user()),
        ]);
    }
}
