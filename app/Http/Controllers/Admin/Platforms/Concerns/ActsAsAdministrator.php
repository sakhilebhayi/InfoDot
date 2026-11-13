<?php

namespace App\Http\Controllers\Admin\Platforms\Concerns;

use App\Services\Platforms\EntitlementRefused;
use Closure;
use Illuminate\Http\RedirectResponse;

/** Shared by the admin centre's controllers: run one service call and turn a refusal into the sentence the page shows. */
trait ActsAsAdministrator
{
    /** The reason every change must carry; kept in the audit trail. */
    protected const REASON = ['required', 'string', 'min:10', 'max:255'];

    protected const REASON_MESSAGES = [
        'reason.required' => 'Write why the change is being made. It is kept in the audit trail.',
        'reason.min' => 'Say a little more about why: at least 10 characters.',
    ];

    /** @param  Closure(): string  $work  returns the sentence to show when it worked */
    protected function attempt(RedirectResponse $back, Closure $work): RedirectResponse
    {
        try {
            return $back->with('status', $work());
        } catch (EntitlementRefused $e) {
            return $back->withInput()->withErrors(['refused' => $e->getMessage()]);
        }
    }
}
