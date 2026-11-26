<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * R-59 Phase 1B: one onboarding step reached by one person (for one business), written once.
 *
 * @property array<string, mixed>|null $meta details of the step (R-73: 'from', the homepage button a sign-up came from)
 */
class OnboardingEvent extends Model
{
    public const CREATED_AT = null;

    public const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['meta' => 'array', 'occurred_at' => 'datetime'];
    }
}
