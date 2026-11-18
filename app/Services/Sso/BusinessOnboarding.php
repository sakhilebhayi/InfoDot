<?php

namespace App\Services\Sso;

use App\Models\User;

/** R-54: whether a person still has to set up a business before a platform that is for businesses can open. */
class BusinessOnboarding
{
    /** A person with only the personal workspace (no company they own or belong to) has no business yet. */
    public function needsBusiness(User $user): bool
    {
        // Fresh queries, not the loaded relations: a second tap on the same request cycle must see the business just made.
        return ! $user->ownedTeams()->where('personal_team', false)->exists()
            && ! $user->teams()->where('personal_team', false)->exists();
    }
}
