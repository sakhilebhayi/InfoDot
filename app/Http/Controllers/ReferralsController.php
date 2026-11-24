<?php

namespace App\Http\Controllers;

use App\Models\Referral;
use App\Models\ReferralReward;
use App\Models\ReferralTier;
use App\Services\Growth\Referrals;
use App\Support\GettingStarted;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** R-59 Phase 1C: a business owner's own referral code, link and progress. Counts only: no referred person is named. */
class ReferralsController extends Controller
{
    public function show(Request $request, Referrals $referrals): View
    {
        $user = $request->user();
        $team = GettingStarted::managedBusiness($user);
        abort_if($team === null || ! $user->ownsTeam($team), 404);

        $code = $referrals->codeFor($team);
        $states = Referral::where('referrer_team_id', $team->id)->selectRaw('state, count(*) as n')->groupBy('state')->pluck('n', 'state');

        return view('referrals.show', [
            'team' => $team,
            'link' => url('/register').'?ref='.$code->code,
            'code' => $code->code,
            'states' => $states,
            'tiers' => ReferralTier::where('active', true)->orderBy('referrals_required')->get(),
            'rewards' => ReferralReward::where('referrer_team_id', $team->id)->get(),
            'activated' => (int) ($states[Referral::ACTIVATED] ?? 0),
            'holdingDays' => (int) config('referrals.holding_days'),
        ]);
    }
}
