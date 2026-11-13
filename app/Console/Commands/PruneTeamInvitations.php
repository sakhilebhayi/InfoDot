<?php

namespace App\Console\Commands;

use App\Models\TeamInvitation;
use Illuminate\Console\Command;

/** Removes company invitations that ran out more than a set number of days ago (30 by default). Counts only in the output. */
class PruneTeamInvitations extends Command
{
    protected $signature = 'invitations:prune {--days= : Days after expiry before an invitation is removed}';

    protected $description = 'Delete company invitations that expired more than 30 days ago';

    public function handle(): int
    {
        $days = max(1, (int) ($this->option('days') ?? config('licensing.invitation_prune_days', 30)));
        $removed = TeamInvitation::whereNotNull('expires_at')->where('expires_at', '<', now()->subDays($days))->delete();
        $this->info("Removed {$removed} expired invitation(s).");

        return self::SUCCESS;
    }
}
