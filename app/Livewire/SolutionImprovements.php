<?php

namespace App\Livewire;

use App\Models\SolutionImprovement;
use App\Models\Solutions;
use App\Models\SolutionVersion;
use App\Models\Steps;
use App\Services\Attachments\AttachmentService;
use App\Services\Knowledge\AnswerService;
use App\Services\Knowledge\ImprovementService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Suggest-an-improvement, the author's review desk, and the version history on a solution page (R-16 phases 4-5).
 * The browser asks; ImprovementService decides, so a hand-made Livewire call can do no more than the buttons.
 */
class SolutionImprovements extends Component
{
    public int $solutionId;

    public bool $proposing = false;

    public array $form = ['target' => 'description', 'step_id' => '', 'heading' => '', 'body' => '', 'rationale' => ''];

    /** JSON the attachment widget keeps current for the proposal. */
    public string $attachmentsPayload = '';

    /** The author's note per proposal id (optional on reject, required to ask for clarification). */
    public array $notes = [];

    public ?int $revising = null;

    public array $revision = ['body' => '', 'rationale' => ''];

    protected array $rules = [
        'form.target' => 'required|in:description,step,new-step',
        'form.step_id' => 'nullable|integer',
        'form.heading' => 'nullable|string|max:255',
        'form.body' => 'required|string|min:10|max:5000',
        'form.rationale' => 'required|string|min:10|max:2000',
    ];

    protected array $validationAttributes = [
        'form.body' => 'proposed text', 'form.rationale' => 'reason', 'form.heading' => 'heading',
    ];

    public function mount(int $solutionId): void
    {
        $this->solutionId = $solutionId;
    }

    private function solution(): Solutions
    {
        $solution = Solutions::find($this->solutionId);
        abort_unless($solution !== null && Auth::check() && app(AnswerService::class)->canSeeContent($solution, Auth::user()), 404);

        return $solution;
    }

    public function propose(ImprovementService $service, AttachmentService $attachments): void
    {
        $solution = $this->solution();
        $this->validate();
        $files = AttachmentService::parsePayload($this->attachmentsPayload);

        try {
            $improvement = $service->propose($solution, Auth::user(), [
                'target_kind' => $this->form['target'],
                'step_id' => (int) ($this->form['step_id'] ?: 0),
                'proposed_heading' => $this->form['heading'],
                'proposed_body' => $this->form['body'],
                'rationale' => $this->form['rationale'],
            ]);
        } catch (ValidationException $e) {
            $this->addError('form.body', collect($e->errors())->flatten()->first());

            return;
        }

        $attachments->bind($files, $improvement, Auth::user());

        $this->reset('proposing', 'attachmentsPayload');
        $this->form = ['target' => 'description', 'step_id' => '', 'heading' => '', 'body' => '', 'rationale' => ''];
        $this->dispatch('improvement-attachments-clear');
        session()->flash('improvement-status', 'Your improvement was sent to the author. Nothing changes unless they approve it.');
    }

    public function approve(int $id, ImprovementService $service): void
    {
        try {
            $service->approve($this->open($id), Auth::user());
            session()->flash('improvement-status', 'Approved. The solution is now at a new version, credited to the contributor.');
        } catch (ValidationException $e) {
            $this->addError('decision-'.$id, collect($e->errors())->flatten()->first());
        }
    }

    public function reject(int $id, ImprovementService $service): void
    {
        $service->reject($this->open($id), Auth::user(), (string) ($this->notes[$id] ?? ''));
        unset($this->notes[$id]);
    }

    public function clarify(int $id, ImprovementService $service): void
    {
        if (trim((string) ($this->notes[$id] ?? '')) === '') {
            $this->addError('decision-'.$id, 'Write what you need clarified in the note, then send it.');

            return;
        }

        $service->requestClarification($this->open($id), Auth::user(), (string) $this->notes[$id]);
        unset($this->notes[$id]);
    }

    public function startRevise(int $id): void
    {
        $improvement = $this->open($id);
        abort_unless($improvement->proposer_id === Auth::id(), 403);
        $this->revising = $id;
        $this->revision = ['body' => $improvement->proposed_body, 'rationale' => $improvement->rationale];
    }

    public function saveRevision(ImprovementService $service): void
    {
        abort_unless($this->revising !== null, 422);
        $this->validate([
            'revision.body' => 'required|string|min:10|max:5000',
            'revision.rationale' => 'required|string|min:10|max:2000',
        ]);

        $service->revise($this->open($this->revising), Auth::user(), $this->revision['body'], $this->revision['rationale']);
        $this->reset('revising', 'revision');
        session()->flash('improvement-status', 'Your revised proposal went back to the author.');
    }

    private function open(int $id): SolutionImprovement
    {
        return SolutionImprovement::where('solution_id', $this->solutionId)->findOrFail($id);
    }

    public function render(): View
    {
        $solution = $this->solution();
        $user = Auth::user();
        $isAuthor = $solution->user_id === $user->id;

        $mineOrAll = SolutionImprovement::with(['proposer', 'step', 'attachments'])
            ->where('solution_id', $solution->id)
            ->when(! $isAuthor, fn ($q) => $q->where('proposer_id', $user->id))
            ->latest()->limit(20)->get();

        return view('livewire.solution-improvements', [
            'solution' => $solution,
            'isAuthor' => $isAuthor,
            'proposals' => $mineOrAll,
            'steps' => Steps::where('solution_id', $solution->id)->orderBy('id')->get(),
            'versions' => SolutionVersion::with('contributor')->where('solution_id', $solution->id)->orderByDesc('version')->get(),
        ]);
    }
}
