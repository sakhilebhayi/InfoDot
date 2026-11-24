<?php

namespace App\Console\Commands;

use App\Models\Referral;
use App\Services\Growth\Referrals;
use Illuminate\Console\Command;

/** R-59 Phase 1C: moves referrals forward, settles milestone rewards and releases the ones whose holding period is over. Idempotent. */
class ReferralsAdvance extends Command
{
    protected $signature = 'referrals:advance';

    protected $description = 'Advance referrals, settle rewards and release held rewards';

    public function handle(Referrals $referrals): int
    {
        $teams = [];

        Referral::whereIn('state', [Referral::REGISTERED, Referral::VERIFIED, Referral::ACTIVATED])->with('referred')->chunkById(200, function ($batch) use ($referrals, &$teams) {
            foreach ($batch as $referral) {
                if ($referral->referred?->isSuspended()) {
                    $referrals->reverse($referral, 'account suspended');

                    continue;
                }
                $referrals->advance($referral);
                $teams[$referral->referrer_team_id] = true;
            }
        });

        foreach (array_keys($teams) as $teamId) {
            $referrals->settleRewards($teamId);
        }

        $due = $referrals->release();
        $this->info('Referrals checked for '.count($teams).' businesses; '.$due.' rewards now due.');

        return self::SUCCESS;
    }
}
