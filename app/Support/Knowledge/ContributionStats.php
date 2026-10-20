<?php

namespace App\Support\Knowledge;

use App\Models\Answer;
use App\Models\Questions;
use App\Models\User;

/**
 * What a person has contributed, counted from InfoDot's own records (never from the points service, which only knows
 * what was rewarded). For anyone but the owner only public items count, so the profile never hints at private work.
 */
final class ContributionStats
{
    /** @return array{questions: int, answers: int, accepted: int} */
    public static function for(User $user, bool $ownerView): array
    {
        $questions = Questions::withoutGlobalScopes()->where('user_id', $user->id)
            ->when(! $ownerView, fn ($q) => $q->where('visibility', Questions::VISIBILITY_PUBLIC))->count();

        $answers = Answer::query()->where('user_id', $user->id)->where('status', Answer::PUBLISHED)
            ->when(! $ownerView, fn ($q) => $q->whereHas('question', fn ($qq) => $qq->where('visibility', Questions::VISIBILITY_PUBLIC)));

        return [
            'questions' => $questions,
            'answers' => (clone $answers)->count(),
            // An answer to your own question that you "accepted" yourself is not help given: it never counts.
            'accepted' => (clone $answers)->whereNotNull('accepted_at')->where('is_self_answer', false)->count(),
        ];
    }
}
