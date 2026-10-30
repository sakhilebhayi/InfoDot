<?php

namespace App\Livewire;

use App\Models\SolutionConfirmation;
use App\Models\Solutions;
use App\Models\UserNotice;
use App\Services\Knowledge\AnswerService;
use App\Services\Knowledge\InAppNotices;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Livewire\Component;

/**
 * "I tried this and it works" on a solution (R-16 phase 1). A toggle, not a like: one confirmation per person, the
 * author cannot confirm their own work, and the count is presented as implementation confirmations. Optional feedback
 * (industry, what was implemented, results) can be added after confirming — never required.
 */
class SolutionConfirmations extends Component
{
    public int $solutionId;

    public bool $showFeedback = false;

    public array $feedback = ['business_industry' => '', 'applied_part' => '', 'results' => '', 'timeframe' => '', 'recommend' => ''];

    protected array $rules = [
        'feedback.business_industry' => 'nullable|string|max:100',
        'feedback.applied_part' => 'nullable|string|max:255',
        'feedback.results' => 'nullable|string|max:1000',
        'feedback.timeframe' => 'nullable|string|max:100',
        'feedback.recommend' => 'nullable|in:,yes,no',
    ];

    public function mount(int $solutionId): void
    {
        $this->solutionId = $solutionId;

        $mine = $this->mine();
        if ($mine !== null) {
            $this->feedback = [
                'business_industry' => (string) $mine->business_industry,
                'applied_part' => (string) $mine->applied_part,
                'results' => (string) $mine->results,
                'timeframe' => (string) $mine->timeframe,
                'recommend' => $mine->recommend === null ? '' : ($mine->recommend ? 'yes' : 'no'),
            ];
        }
    }

    /** Livewire restores nothing scoped: every call asks again whether this person may still see the solution. */
    private function solution(): Solutions
    {
        $solution = Solutions::find($this->solutionId);
        abort_unless($solution !== null && Auth::check() && app(AnswerService::class)->canSeeContent($solution, Auth::user()), 404);

        return $solution;
    }

    private function mine(): ?SolutionConfirmation
    {
        return SolutionConfirmation::where('solution_id', $this->solutionId)->where('user_id', Auth::id())->first();
    }

    public function toggle(): void
    {
        $solution = $this->solution();

        if ($solution->user_id === Auth::id()) {
            $this->addError('confirmation', 'You wrote this solution, so your own confirmation would not mean much. Ask someone who tried it.');

            return;
        }

        if (! RateLimiter::attempt('confirmations:'.Auth::id(), 30, fn () => true, 3600)) {
            $this->addError('confirmation', 'You are confirming solutions very quickly. Try again later.');

            return;
        }

        $mine = $this->mine();

        if ($mine !== null) {
            $mine->delete();
            $this->showFeedback = false;

            return;
        }

        SolutionConfirmation::firstOrCreate(['solution_id' => $solution->id, 'user_id' => Auth::id()]);
        $this->showFeedback = true;

        // The author hears that someone tried their work and it worked — once per person, however often the button is
        // toggled. Never able to fail the confirmation itself.
        try {
            $ref = 'infodot:tried:'.$solution->id.':'.Auth::id();
            if (! UserNotice::where('user_id', $solution->user_id)->where('kind', UserNotice::SOLUTION_CONFIRMED)->where('ref', $ref)->exists()) {
                app(InAppNotices::class)->record(UserNotice::SOLUTION_CONFIRMED, $solution->user, 'solution', $solution->id, $ref, Auth::user());
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    public function saveFeedback(): void
    {
        $this->solution();
        $mine = $this->mine();
        abort_unless($mine !== null, 403);

        $this->validate();

        $mine->update([
            'business_industry' => trim($this->feedback['business_industry']) ?: null,
            'applied_part' => trim($this->feedback['applied_part']) ?: null,
            'results' => trim($this->feedback['results']) ?: null,
            'timeframe' => trim($this->feedback['timeframe']) ?: null,
            'recommend' => $this->feedback['recommend'] === '' ? null : $this->feedback['recommend'] === 'yes',
        ]);

        $this->showFeedback = false;
        session()->flash('confirmation-status', 'Thanks — your experience is recorded with your confirmation.');
    }

    public function render(): View
    {
        $solution = $this->solution();

        $confirmations = SolutionConfirmation::where('solution_id', $solution->id)->with('user')->latest()->get();
        $mine = $confirmations->firstWhere('user_id', Auth::id());

        // Aggregated context only once there is enough of it to mean something and to not point at one person.
        $insights = null;
        if ($confirmations->count() >= 5) {
            $industries = $confirmations->pluck('business_industry')->filter()->map(fn ($i) => mb_strtolower(trim($i)))->unique();
            $withResults = $confirmations->whereNotNull('results')->count();
            $recommend = $confirmations->whereNotNull('recommend');
            $insights = [
                'businesses' => $confirmations->count(),
                'industries' => $industries->count(),
                'with_results' => $withResults,
                'would_recommend' => $recommend->count() >= 5 ? $recommend->where('recommend', true)->count().' of '.$recommend->count() : null,
            ];
        }

        return view('livewire.solution-confirmations', [
            'solution' => $solution,
            'confirmations' => $confirmations,
            'mine' => $mine,
            'isAuthor' => $solution->user_id === Auth::id(),
            'insights' => $insights,
        ]);
    }
}
