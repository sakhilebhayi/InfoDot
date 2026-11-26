<?php

namespace App\Actions\Fortify;

use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * The rules for a new password: the app-wide default (R-72, S-03: at least 12 characters and not found in a public breach,
     * see AppServiceProvider::boot), typed twice.
     *
     * @return array<int, mixed>
     */
    protected function passwordRules(): array
    {
        return ['required', 'string', Password::defaults(), 'confirmed'];
    }
}
