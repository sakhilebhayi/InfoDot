<?php

namespace App\Services\Knowledge;

use App\Events\Knowledge\ContributionApproved;
use App\Models\SolutionImprovement;
use App\Models\Solutions;
use App\Models\SolutionVersion;
use App\Models\Steps;
use App\Models\User;
use App\Models\UserNotice;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Collaborative improvements with version history (R-16 phases 4-5). Anyone who may see a solution can propose a
 * better description, a better step, or a missing step; only the original author decides. Approval happens under a
 * row lock against the version the proposal was written for, writes the next version (the previous one stays
 * readable), credits the improver without transferring ownership, and clears the moderator-verified mark because the
 * verified content changed. Nothing a proposal contains touches the solution before approval.
 */
class ImprovementService
{
    public const PER_HOUR = 10;

    /** @param array{target_kind: string, step_id?: int|null, proposed_heading?: string|null, proposed_body: string, rationale: string} $data */
    public function propose(Solutions $solution, User $proposer, array $data): SolutionImprovement
    {
        abort_unless(app(AnswerService::class)->canSeeContent($solution, $proposer), 403);
        abort_if($solution->isHidden(), 403);

        $solution->refresh(); // the caller's copy may predate the latest approved version

        if (! RateLimiter::attempt('improvements:'.$proposer->id, self::PER_HOUR, fn () => true, 3600)) {
            throw ValidationException::withMessages(['proposed_body' => 'You are proposing a lot of changes in a short time. Try again later.']);
        }

        $target = $data['target_kind'];
        $stepId = null;

        if ($target === 'step') {
            $step = Steps::where('solution_id', $solution->id)->find((int) ($data['step_id'] ?? 0));
            if (! $step) {
                throw ValidationException::withMessages(['step_id' => 'Pick the step this improves.']);
            }
            $stepId = $step->id;
        } elseif ($target === 'new-step' && trim((string) ($data['proposed_heading'] ?? '')) === '') {
            throw ValidationException::withMessages(['proposed_heading' => 'A new step needs a heading.']);
        } elseif (! in_array($target, ['description', 'step', 'new-step'], true)) {
            throw ValidationException::withMessages(['target_kind' => 'Pick what this improves.']);
        }

        $improvement = new SolutionImprovement([
            'target_kind' => $target,
            'step_id' => $stepId,
            'proposed_heading' => trim((string) ($data['proposed_heading'] ?? '')) ?: null,
            'proposed_body' => trim($data['proposed_body']),
            'rationale' => trim($data['rationale']),
        ]);
        $improvement->solution_id = $solution->id;
        $improvement->proposer_id = $proposer->id;
        $improvement->base_version = (int) ($solution->version ?? 1);
        $improvement->status = SolutionImprovement::PENDING;
        $improvement->save();

        $this->tell(UserNotice::IMPROVEMENT_PROPOSED, $solution->user, $solution, $improvement, $proposer);

        return $improvement;
    }

    /** The proposer revises a pending or clarify proposal; it goes back to pending and the author hears again after a clarify. */
    public function revise(SolutionImprovement $improvement, User $proposer, string $body, string $rationale): void
    {
        abort_unless($improvement->proposer_id === $proposer->id && $improvement->isOpen(), 403);

        $wasClarify = $improvement->status === SolutionImprovement::CLARIFY;
        $improvement->forceFill([
            'proposed_body' => trim($body),
            'rationale' => trim($rationale),
            'status' => SolutionImprovement::PENDING,
        ])->save();

        if ($wasClarify) {
            $solution = $improvement->solution;
            $this->tell(UserNotice::IMPROVEMENT_PROPOSED, $solution->user, $solution, $improvement, $proposer);
        }
    }

    public function approve(SolutionImprovement $improvement, User $author): void
    {
        DB::transaction(function () use ($improvement, $author) {
            $solution = Solutions::withoutGlobalScopes()->lockForUpdate()->findOrFail($improvement->solution_id);
            abort_unless($solution->user_id === $author->id, 403);

            $improvement = SolutionImprovement::lockForUpdate()->findOrFail($improvement->id);
            abort_unless($improvement->isOpen(), 409);

            // Another improvement landed since this one was written: the text it proposes may no longer fit. Nothing
            // is applied until the proposer (or the author, reading both) has re-checked it against the new version.
            if ($improvement->base_version < (int) $solution->version) {
                throw ValidationException::withMessages([
                    'improvement' => 'The solution has changed since this was proposed (it is written against version '.$improvement->base_version.', the solution is at '.$solution->version.'). Ask the proposer to re-check it, or read it against the current text before approving.',
                ]);
            }

            // The content as it stands becomes a kept version, once, so the history always starts at the original.
            SolutionVersion::firstOrCreate(
                ['solution_id' => $solution->id, 'version' => (int) $solution->version],
                [
                    'solution_title' => (string) $solution->solution_title,
                    'solution_description' => $solution->solution_description,
                    'steps' => $this->stepsSnapshot($solution),
                    'author_id' => (int) $solution->user_id,
                    'contributor_id' => null,
                    'summary' => (int) $solution->version === 1 ? 'Original solution' : null,
                    'created_at' => $solution->created_at,
                ],
            );

            // Apply the change.
            if ($improvement->target_kind === 'description') {
                $solution->solution_description = $improvement->proposed_body;
            } elseif ($improvement->target_kind === 'step') {
                $step = Steps::where('solution_id', $solution->id)->findOrFail($improvement->step_id);
                if ($improvement->proposed_heading !== null) {
                    $step->solution_heading = $improvement->proposed_heading;
                }
                $step->solution_body = $improvement->proposed_body;
                $step->save();
            } else { // new-step
                $step = new Steps;
                $step->user_id = $improvement->proposer_id;
                $step->solution_id = $solution->id;
                $step->solution_heading = (string) $improvement->proposed_heading;
                $step->solution_body = $improvement->proposed_body;
                $step->save();
                $improvement->forceFill(['step_id' => $step->id])->save();
            }

            // The moderator's verification covered the previous content; the changed solution starts unverified again.
            $solution->forceFill([
                'version' => (int) $solution->version + 1,
                'steps' => Steps::where('solution_id', $solution->id)->count(),
                'verified_at' => null,
                'verified_by' => null,
            ])->save();

            SolutionVersion::create([
                'solution_id' => $solution->id,
                'version' => (int) $solution->version,
                'solution_title' => (string) $solution->solution_title,
                'solution_description' => $solution->solution_description,
                'steps' => $this->stepsSnapshot($solution),
                'author_id' => (int) $solution->user_id,
                'contributor_id' => (int) $improvement->proposer_id,
                'summary' => Str::limit($improvement->rationale, 240),
                'created_at' => now(),
            ]);

            // Supporting files the proposer attached become part of what they now support.
            $improvement->attachments()->get()->each(function ($attachment) use ($improvement, $solution) {
                $target = $improvement->target_kind === 'description' ? $solution : Steps::find($improvement->step_id);
                if ($target) {
                    $attachment->forceFill(['attachable_type' => $target->getMorphClass(), 'attachable_id' => $target->getKey()])->save();
                }
            });

            $improvement->forceFill(['status' => SolutionImprovement::APPROVED, 'decided_by' => $author->id, 'decided_at' => now()])->save();

            $solution->searchable();

            // The improver earns the existing approved-contribution reward — only when someone else approved their work.
            if ($improvement->proposer_id !== $author->id) {
                $proposer = $improvement->proposer;
                DB::afterCommit(fn () => event(new ContributionApproved('infodot:improvement:'.$improvement->id, [
                    'author_uuid' => $proposer->uuid,
                    'tenant_team_id' => $solution->team_id,
                    'visibility' => $solution->visibility,
                    'event_key' => 'solution_improvement:'.$improvement->id,
                    'kind' => 'solution_improvement',
                    'approved_by_uuid' => $author->uuid,
                ])));
            }

            $this->tell(UserNotice::IMPROVEMENT_APPROVED, $improvement->proposer, $solution, $improvement, $author);
        });
    }

    public function reject(SolutionImprovement $improvement, User $author, ?string $note): void
    {
        $this->decide($improvement, $author, SolutionImprovement::REJECTED, $note, UserNotice::IMPROVEMENT_REJECTED);
    }

    public function requestClarification(SolutionImprovement $improvement, User $author, ?string $note): void
    {
        $this->decide($improvement, $author, SolutionImprovement::CLARIFY, $note, UserNotice::IMPROVEMENT_CLARIFY);
    }

    private function decide(SolutionImprovement $improvement, User $author, string $status, ?string $note, string $noticeKind): void
    {
        $solution = $improvement->solution;
        abort_unless($solution && $solution->user_id === $author->id, 403);
        abort_unless($improvement->isOpen(), 409);

        $improvement->forceFill([
            'status' => $status,
            'decision_note' => Str::limit(trim((string) $note), 1997) ?: null,
            'decided_by' => $author->id,
            'decided_at' => $status === SolutionImprovement::CLARIFY ? null : now(),
        ])->save();

        $this->tell($noticeKind, $improvement->proposer, $solution, $improvement, $author);
    }

    /** @return list<array{heading: string, body: string}> the whole procedure right now, in order */
    private function stepsSnapshot(Solutions $solution): array
    {
        return Steps::where('solution_id', $solution->id)->orderBy('id')->get()
            ->map(fn (Steps $step) => ['heading' => (string) $step->solution_heading, 'body' => (string) $step->solution_body])
            ->values()->all();
    }

    private function tell(string $kind, ?User $recipient, Solutions $solution, SolutionImprovement $improvement, User $actor): void
    {
        try {
            app(InAppNotices::class)->record($kind, $recipient, 'solution', $solution->id, 'infodot:improvement:'.$improvement->id.':'.$improvement->status, $actor);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
