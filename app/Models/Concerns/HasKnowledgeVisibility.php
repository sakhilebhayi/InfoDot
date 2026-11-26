<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Who may see a question or solution.
 *
 * Public content is visible to everyone, including signed-out visitors. Company
 * content is visible only to people acting for that company (their current team),
 * and its author always sees their own. This is a global scope so a forgotten
 * where() in a new controller cannot leak another company's content; code that
 * truly needs everything (a migration, a job) opts out with withoutGlobalScope().
 */
trait HasKnowledgeVisibility
{
    public const VISIBILITY_PUBLIC = 'public';

    public const VISIBILITY_COMPANY = 'company';

    protected static function bootHasKnowledgeVisibility(): void
    {
        static::addGlobalScope('knowledge-visibility', function (Builder $builder): void {
            // Queue workers and artisan commands have no person behind them.
            if (! Auth::check() && app()->runningInConsole() && ! app()->runningUnitTests()) {
                return;
            }

            $table = $builder->getModel()->getTable();
            $user = Auth::user();

            $builder->where(function (Builder $q) use ($table, $user) {
                $q->where($table.'.visibility', self::VISIBILITY_PUBLIC);

                if ($user) {
                    $q->orWhere($table.'.user_id', $user->id);

                    if ($user->current_team_id) {
                        $q->orWhere($table.'.team_id', $user->current_team_id);
                    }
                }
            });
        });
    }

    public function isPublic(): bool
    {
        return $this->visibility === self::VISIBILITY_PUBLIC;
    }

    /**
     * R-72 (E-08): the same rule as the scope, for one person, wherever the code runs. The scope is skipped in queue workers and
     * commands (nobody is signed in there), so anything they send about a question or solution asks this instead.
     */
    public function visibleTo(?User $user): bool
    {
        if ($this->isPublic()) {
            return true;
        }

        if ($user === null) {
            return false;
        }

        return (int) $this->user_id === (int) $user->getKey()
            || ($this->team_id !== null && $user->allTeams()->contains('id', (int) $this->team_id));
    }
}
