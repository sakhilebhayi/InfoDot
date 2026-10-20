<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * Content a moderator can hide (questions, answers, solutions).
 *
 * Hidden is a reversible flag. Like the visibility scope, this is a global scope so a forgotten where() in a new list
 * or search cannot show hidden content: everyone sees only what is not hidden, except its author (who sees it with a
 * notice) and moderators. Code that truly needs everything opts out with withoutGlobalScope('moderation-visibility').
 */
trait IsModerated
{
    protected static function bootIsModerated(): void
    {
        static::addGlobalScope('moderation-visibility', function (Builder $builder): void {
            // Queue workers and artisan commands have no person behind them.
            if (! Auth::check() && app()->runningInConsole() && ! app()->runningUnitTests()) {
                return;
            }

            $user = Auth::user();

            if ($user && $user->isModerator()) {
                return;
            }

            $table = $builder->getModel()->getTable();

            $builder->where(function (Builder $q) use ($table, $user) {
                $q->whereNull($table.'.hidden_at');

                if ($user) {
                    $q->orWhere($table.'.user_id', $user->id);
                }
            });
        });
    }

    public function initializeIsModerated(): void
    {
        $this->mergeCasts(['hidden_at' => 'datetime']);
    }

    public function isHidden(): bool
    {
        return $this->hidden_at !== null;
    }
}
