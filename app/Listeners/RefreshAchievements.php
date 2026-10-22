<?php

namespace App\Listeners;

use App\Events\Knowledge\AnswerAcceptanceRevoked;
use App\Events\Knowledge\AnswerAccepted;
use App\Events\Knowledge\AnswerPublished;
use App\Events\Knowledge\ContentModerationChanged;
use App\Events\Knowledge\QuestionSubmitted;
use App\Models\Answer;
use App\Models\Questions;
use App\Models\Solutions;
use App\Models\User;
use App\Services\Knowledge\AchievementService;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Re-checks the milestones of everyone a knowledge event can have changed. It runs in the request, after the change is
 * saved, and can never fail it: milestones are a courtesy, and the backfill command repairs any that were missed.
 */
class RefreshAchievements
{
    public function __construct(private readonly AchievementService $achievements) {}

    public function handle(object $event): void
    {
        try {
            $this->people($event)->each(fn (User $user) => $this->achievements->sync($user));
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** @return Collection<int, User> */
    private function people(object $event): Collection
    {
        $facts = $event->facts ?? [];

        if ($event instanceof ContentModerationChanged) {
            return User::whereIn('id', $this->ownersOf((string) ($facts['kind'] ?? ''), (int) ($facts['id'] ?? 0)))->get();
        }

        if ($event instanceof QuestionSubmitted || $event instanceof AnswerPublished || $event instanceof AnswerAccepted || $event instanceof AnswerAcceptanceRevoked) {
            return User::where('uuid', $facts['author_uuid'] ?? null)->get();
        }

        return collect();
    }

    /**
     * Whose work counts differently now. Hiding a question takes its answers out of play too, so their authors are included.
     *
     * @return list<int>
     */
    private function ownersOf(string $kind, int $id): array
    {
        return match ($kind) {
            'question' => array_values(array_unique(array_merge(
                Questions::withoutGlobalScopes()->whereKey($id)->pluck('user_id')->all(),
                Answer::withoutGlobalScopes()->where('question_id', $id)->pluck('user_id')->all(),
            ))),
            'answer' => Answer::withoutGlobalScopes()->whereKey($id)->pluck('user_id')->all(),
            'solution' => Solutions::withoutGlobalScopes()->whereKey($id)->pluck('user_id')->all(),
            default => [],
        };
    }
}
