<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * InfoDot is Jetstream-teams-scoped. Objects/Files/Folders (the "Team
 * Drive" storage layer, see the objects/files/folders migrations) own a
 * team_id column, so this trait scopes a query against them to the
 * authenticated user's current team by default, mirroring Dot.Notify's
 * HasTeamScope / Dot.Finance's HasUserScope — the goal is that a
 * forgotten where('team_id', ...) call in a future controller can no
 * longer leak another team's rows, because the model itself never
 * returns unscoped results while a user is authenticated with a current
 * team.
 *
 * Deliberately NOT applied to Questions/Solutions/Steps/Comment/Like/
 * Associates: those are genuinely global, publicly-visible community
 * content (a Q&A/knowledge-base feed every user browses, not
 * per-tenant private data) even though several of them carry a user_id
 * column recording authorship — see wiki.md §"Tenant Scope Audit".
 */
trait HasTeamScope
{
    protected static function bootHasTeamScope(): void
    {
        static::addGlobalScope('team', function (Builder $builder): void {
            if (Auth::check() && Auth::user()->currentTeam) {
                $builder->where($builder->getModel()->getTable().'.team_id', Auth::user()->currentTeam->id);
            }
        });
    }
}
