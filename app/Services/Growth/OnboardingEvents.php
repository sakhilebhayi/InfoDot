<?php

namespace App\Services\Growth;

use App\Models\OnboardingEvent;
use App\Models\Team;
use App\Models\User;

/**
 * R-59 Phase 1B: records that a person reached an onboarding step. A step is written once per person and business, so calling this again
 * (a second tap, a retried request, a page reload) changes nothing. Recording must never stop what the person was doing.
 */
class OnboardingEvents
{
    public function record(User $user, string $step, ?Team $team = null, array $meta = []): void
    {
        if (! array_key_exists($step, config('onboarding.steps'))) {
            return;
        }

        try {
            OnboardingEvent::firstOrCreate(
                ['user_id' => $user->id, 'scope' => $team?->id ?? 0, 'step' => $step],
                ['team_id' => $team?->id, 'meta' => $meta === [] ? null : $meta, 'occurred_at' => now()],
            );
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
