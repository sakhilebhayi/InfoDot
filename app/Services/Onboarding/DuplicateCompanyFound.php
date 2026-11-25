<?php

namespace App\Services\Onboarding;

use App\Models\Team;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Raised when a company that is about to be created looks like one that already exists. `own` are companies this person already belongs to (they
 * may be named). `elsewhere` only says that some other company uses the same registration number or website; nothing about it is revealed.
 */
class DuplicateCompanyFound extends RuntimeException
{
    /** @param  Collection<int, Team>  $own */
    public function __construct(public readonly Collection $own, public readonly bool $elsewhere)
    {
        parent::__construct('A company like this already exists.');
    }
}
