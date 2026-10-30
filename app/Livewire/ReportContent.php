<?php

namespace App\Livewire;

use App\Models\ContentReport;
use App\Services\Knowledge\AnswerService;
use App\Services\Knowledge\ModerationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** "Report" on a question or solution (answers report from their own list). AnswerService decides; this only asks. */
class ReportContent extends Component
{
    #[Locked]
    public string $kind;

    #[Locked]
    public int $itemId;

    public bool $open = false;

    public string $reason = 'low_quality';

    public string $detail = '';

    public function mount(string $kind, int $itemId): void
    {
        $this->kind = $kind;
        $this->itemId = $itemId;
    }

    public function send(AnswerService $service, ModerationService $moderation): void
    {
        try {
            $service->report($moderation->find($this->kind, $this->itemId), Auth::user(), $this->reason, $this->detail);
            $this->open = false;
            $this->reset('detail');
            session()->flash('report-status', 'Thanks. A moderator will look at it.');
        } catch (ValidationException $e) {
            $this->addError('report', collect($e->errors())->flatten()->first());
        }
    }

    public function render(ModerationService $moderation): View
    {
        $item = $moderation->find($this->kind, $this->itemId);
        $user = Auth::user();

        return view('livewire.report-content', [
            'mine' => $item->user_id === $user->id,
            'hidden' => $item->isHidden(),
            'reported' => ContentReport::existsFor($item, $user),
        ]);
    }
}
