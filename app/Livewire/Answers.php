<?php

namespace App\Livewire;

use App\Models\Answer;
use App\Models\ContentReport;
use App\Models\Questions;
use App\Models\SolutionRecommendation;
use App\Models\Solutions;
use App\Models\UserNotice;
use App\Services\Attachments\AttachmentService;
use App\Services\Knowledge\AnswerService;
use App\Services\Knowledge\InAppNotices;
use App\Services\Knowledge\MentionService;
use App\Services\Knowledge\TopicBadges;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

/**
 * The formal answers under a question. The browser asks; AnswerService decides (who may answer, accept or
 * report), so a hand-made Livewire call cannot do more than the buttons can.
 */
class Answers extends Component
{
    public Questions $question;

    public string $body = '';

    /** JSON the attachment widget keeps current: [{uuid, caption}, ...]. */
    public string $attachmentsPayload = '';

    /** JSON the @mention box keeps current: [{type, id}, ...]. */
    public string $mentionsPayload = '';

    /** JSON the recommend-a-solution picker keeps current: [{id}, ...]. */
    public string $recommendationsPayload = '';

    public ?int $reporting = null;

    public string $reason = 'low_quality';

    public function mount(Questions $question): void
    {
        $this->question = $question;
    }

    public function publish(AnswerService $service, AttachmentService $attachments): void
    {
        $this->validateOnly('body', ['body' => ['required', 'string']]);
        $items = AttachmentService::parsePayload($this->attachmentsPayload);
        $mentionItems = MentionService::parsePayload($this->mentionsPayload);
        $recommended = $this->recommendedSolutions();

        $answer = $service->publish($this->question, Auth::user(), $this->body);
        $attachments->bind($items, $answer, Auth::user());
        app(MentionService::class)->record($answer, Auth::user(), $mentionItems);
        $this->recommend($answer, $recommended);

        $this->reset('body', 'attachmentsPayload', 'mentionsPayload', 'recommendationsPayload');
        $this->dispatch('answer-attachments-clear');
        session()->flash('answer-status', 'Your answer is posted.');
    }

    /** The solutions the picker chose, re-read under this person's own visibility: what they may not see is not found. */
    /** @return Collection<int, Solutions> */
    private function recommendedSolutions(): Collection
    {
        $json = trim($this->recommendationsPayload);

        if ($json === '' || $json === '[]') {
            return new Collection;
        }

        $rows = json_decode($json, true);

        if (! is_array($rows) || count($rows) > 3) {
            throw ValidationException::withMessages(['recommendations' => 'The recommended solutions could not be read. Remove them and pick again.']);
        }

        $ids = collect($rows)->map(fn ($row) => (int) (is_array($row) ? ($row['id'] ?? 0) : 0))->filter(fn ($id) => $id > 0)->unique()->values();

        return Solutions::whereIn('id', $ids)->get();
    }

    /** @param  Collection<int, Solutions>  $solutions */
    private function recommend(Answer $answer, Collection $solutions): void
    {
        foreach ($solutions as $solution) {
            SolutionRecommendation::firstOrCreate(
                ['answer_id' => $answer->id, 'solution_id' => $solution->id],
                ['recommender_id' => Auth::id(), 'created_at' => now()],
            );

            // The solution's author hears their work was recommended; record() itself skips self-recommendation.
            try {
                app(InAppNotices::class)->record(
                    UserNotice::SOLUTION_RECOMMENDED, $solution->user, 'solution', $solution->id,
                    'infodot:recommendation:'.$answer->id.':'.$solution->id, Auth::user(),
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    public function accept(int $answerId, AnswerService $service): void
    {
        $service->accept($this->answer($answerId), Auth::user());
        $this->question->refresh();
        $this->dispatch('answer-changed', id: $answerId);
    }

    public function revoke(int $answerId, AnswerService $service): void
    {
        $service->revokeAcceptance($this->answer($answerId), Auth::user());
        $this->question->refresh();
        $this->dispatch('answer-changed', id: $answerId);
    }

    public function startReport(int $answerId): void
    {
        $this->reporting = $answerId;
    }

    public function report(AnswerService $service): void
    {
        abort_unless($this->reporting !== null, 422);

        try {
            $service->report($this->answer($this->reporting), Auth::user(), $this->reason);
            $this->reporting = null;
            session()->flash('answer-status', 'Thanks. A moderator will look at it.');
        } catch (ValidationException $e) {
            // Own answer, already hidden, or too many reports this hour: say so next to the form instead of failing.
            $this->addError('report', collect($e->errors())->flatten()->first());
        }
    }

    public function render(AnswerService $service): View
    {
        $user = Auth::user();
        // Livewire restores the question without the visibility scopes: ask again, on every refresh, whether this person may still see it.
        abort_unless($service->canSeeContent($this->question, $user), 404);

        $answers = Answer::where('question_id', $this->question->id)
            ->where('status', Answer::PUBLISHED)
            ->with('user', 'question', 'attachments', 'mentions.mentionedUser', 'mentions.mentionedTeam', 'recommendations.solution.user')
            ->orderByRaw('accepted_at is null')->orderBy('created_at')
            ->get();

        // The answers this person has already reported, so the button is replaced by a plain statement.
        $reported = ContentReport::where('reporter_id', $user->id)
            ->where('reportable_type', (new Answer)->getMorphClass())
            ->whereIn('reportable_id', $answers->pluck('id'))
            ->pluck('reportable_id')->all();

        return view('livewire.answers', [
            'reported' => $reported,
            'answers' => $answers,
            // Specialist badges for the topics this question is filed under: two queries however many answers there are.
            // Display only: the order above never reads them.
            'badges' => app(TopicBadges::class)->forQuestion($this->question, $answers->pluck('user_id')->all()),
            'service' => $service,
            'user' => $user,
            'canAnswer' => $service->canSee($this->question, $user) && ! $this->question->isHidden(),
        ]);
    }

    private function answer(int $id): Answer
    {
        return Answer::where('question_id', $this->question->id)->findOrFail($id);
    }
}
