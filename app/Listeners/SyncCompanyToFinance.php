<?php

namespace App\Listeners;

use App\Services\Licensing\FinanceCompanies;
use Laravel\Jetstream\Events\TeamCreated;
use Laravel\Jetstream\Events\TeamDeleted;
use Laravel\Jetstream\Events\TeamUpdated;

/** After the response is sent, tells Dot.Finance about a company that was created, renamed or deleted. Never slows or fails the request. */
class SyncCompanyToFinance
{
    public function created(TeamCreated $event): void
    {
        $this->later($event->team, false);
    }

    public function updated(TeamUpdated $event): void
    {
        if ($event->team->wasChanged('name')) {
            $this->later($event->team, false);
        }
    }

    public function deleted(TeamDeleted $event): void
    {
        $this->later($event->team, true);
    }

    private function later($team, bool $deleted): void
    {
        if ($team->personal_team) {
            return;
        }

        dispatch(fn () => app(FinanceCompanies::class)->sync($team, $deleted))->afterResponse();
    }
}
