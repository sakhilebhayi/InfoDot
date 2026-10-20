<?php

namespace App\Livewire;

use App\Models\ModerationAction;
use App\Models\ModerationAppeal;
use App\Models\Questions;
use App\Models\Solutions;
use App\Services\Knowledge\ModerationService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * Shown on a question, answer or solution that a moderator hid, to its author and to moderators (nobody else can see the
 * item at all). The author reads why and may appeal once; a moderator may restore. Everything is decided in ModerationService.
 */
class HiddenNotice extends Component
{
    public string $kind;

    public int $itemId;

    public string $appealText = '';

    public function mount(string $kind, int $itemId): void
    {
        $this->kind = $kind;
        $this->itemId = $itemId;
    }

    public function fileAppeal(ModerationService $moderation): void
    {
        try {
            $moderation->fileAppeal($this->item($moderation), Auth::user(), $this->appealText);
            $this->reset('appealText');
            $this->resetErrorBag();
        } catch (ValidationException $e) {
            $this->resetErrorBag();
            $this->addError('appealText', collect($e->errors())->flatten()->first());
        }
    }

    public function restore(ModerationService $moderation): void
    {
        $item = $this->item($moderation);
        $moderation->restore($item, Auth::user());

        // The rest of the page was drawn while the item was hidden, so load it again.
        $this->redirect(match (true) {
            $item instanceof Questions => route('questions.view', ['qid' => $item->id]),
            $item instanceof Solutions => route('solutions.view', ['id' => $item->id]),
            default => route('questions.view', ['qid' => $item->question_id]).'#answer-'.$item->id,
        });
    }

    private function item(ModerationService $moderation): Model
    {
        return $moderation->find($this->kind, $this->itemId);
    }

    public function render(ModerationService $moderation): View
    {
        $user = Auth::user();
        $item = $this->item($moderation);
        $isAuthor = $item->user_id === $user->id;
        $show = $isAuthor || $user->isModerator();

        $hide = null;
        $appeal = null;
        $restoredAppeal = null;

        if ($show) {
            $morph = ['subject_type' => $item->getMorphClass(), 'subject_id' => $item->getKey()];

            if ($item->isHidden()) {
                $hide = ModerationAction::where($morph)->where('action', ModerationAction::HIDDEN)->orderByDesc('id')->first();
                $appeal = $hide ? ModerationAppeal::where($morph)->where('hide_action_id', $hide->id)->first() : null;
            } elseif ($isAuthor) {
                $restoredAppeal = ModerationAppeal::where($morph)->where('status', ModerationAppeal::UPHELD)->orderByDesc('id')->first();
            }
        }

        return view('livewire.hidden-notice', [
            'item' => $item,
            'show' => $show,
            'isAuthor' => $isAuthor,
            'hide' => $hide,
            'appeal' => $appeal,
            'restoredAppeal' => $restoredAppeal,
            'canRestore' => $item->isHidden() && $moderation->canModerate($item, $user),
        ]);
    }
}
