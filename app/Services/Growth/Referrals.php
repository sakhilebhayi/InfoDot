<?php

namespace App\Services\Growth;

use App\Models\AcquisitionTouch;
use App\Models\OnboardingEvent;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\ReferralEvent;
use App\Models\ReferralReward;
use App\Models\ReferralTier;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * R-59 Phase 1C: the referral programme. A business has one code; a person who registers through it is attributed once (first valid
 * touch, inside the window); the referral advances registered, verified, activated; milestone rewards are held, then become due;
 * a reversal takes back what is not yet sent. Every write is idempotent, so a retried command or request never repeats a reward.
 * Nothing here recruits recruiters: rewards are only for businesses that were acquired and became active.
 */
class Referrals
{
    public function codeFor(Team $team): ReferralCode
    {
        $existing = ReferralCode::where('team_id', $team->id)->first();
        if ($existing) {
            return $existing;
        }

        for ($i = 0; $i < 5; $i++) {
            try {
                return ReferralCode::create(['team_id' => $team->id, 'code' => $this->newCode()]);
            } catch (UniqueConstraintViolationException) {
                $again = ReferralCode::where('team_id', $team->id)->first();
                if ($again) {
                    return $again;
                }
            }
        }

        throw new \RuntimeException('Could not issue a referral code.');
    }

    /** Called once after an account is created. Never blocks sign-up. */
    public function attribute(User $user): ?Referral
    {
        try {
            return $this->attributeInner($user);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    private function attributeInner(User $user): ?Referral
    {
        $touch = AcquisitionTouch::where('user_id', $user->id)->first();
        if ($touch === null || $touch->channel !== 'referral' || blank($touch->referral_code)) {
            return null;
        }

        $code = ReferralCode::whereRaw('lower(code) = ?', [Str::lower($touch->referral_code)])->first();
        if ($code === null) {
            return null; // an unknown code is only text; nothing is credited to anyone
        }

        $now = now();
        $reason = $this->refusal($user, $code, $touch);
        $referral = null;

        try {
            $referral = Referral::create([
                'referral_code_id' => $code->id,
                'referrer_team_id' => $code->team_id,
                'referred_user_id' => $user->id,
                'state' => $reason === null ? Referral::REGISTERED : Referral::REFUSED,
                'reason' => $reason,
                'registered_at' => $now,
            ]);
        } catch (UniqueConstraintViolationException) {
            return Referral::where('referred_user_id', $user->id)->first(); // a duplicate is never credited twice
        }

        $this->trail($referral->id, null, $reason === null ? 'registered' : 'refused', $reason);

        return $referral;
    }

    private function refusal(User $user, ReferralCode $code, AcquisitionTouch $touch): ?string
    {
        $owner = $code->team?->owner;

        if (! $code->active || $owner === null) {
            return 'code not active';
        }
        if ($touch->touched_at === null || $touch->touched_at->lt(now()->subDays((int) config('referrals.window_days')))) {
            return 'outside the attribution window';
        }
        if ($owner->id === $user->id || Str::lower($owner->email) === Str::lower($user->email)) {
            return 'self referral';
        }
        if ($this->sharesPrivateDomain($owner->email, $user->email)) {
            return 'same email domain';
        }
        $today = Referral::where('referral_code_id', $code->id)->where('registered_at', '>=', now()->startOfDay())->count();
        if ($today >= (int) config('referrals.daily_cap_per_code')) {
            return 'daily limit for this code';
        }

        return null;
    }

    private function sharesPrivateDomain(string $a, string $b): bool
    {
        $domain = fn (string $email): string => Str::lower((string) Str::after($email, '@'));
        $da = $domain($a);

        return $da !== '' && $da === $domain($b) && ! in_array($da, config('referrals.public_email_domains'), true);
    }

    /** Moves one referral as far forward as the facts allow. */
    public function advance(Referral $referral): Referral
    {
        if (! in_array($referral->state, [Referral::REGISTERED, Referral::VERIFIED], true)) {
            return $referral;
        }

        $user = $referral->referred;
        if ($user === null || $user->isSuspended()) {
            return $referral;
        }

        if ($referral->state === Referral::REGISTERED && $this->isVerified($user)) {
            $referral->forceFill(['state' => Referral::VERIFIED, 'verified_at' => now()])->save();
            $this->trail($referral->id, null, 'verified');
        }

        if ($referral->state === Referral::VERIFIED && $this->isActivated($user, $referral)) {
            $referral->forceFill(['state' => Referral::ACTIVATED, 'activated_at' => now()])->save();
            $this->trail($referral->id, null, 'activated');
        }

        return $referral;
    }

    /** Decision D4: confirmed email and a business that has described itself. */
    public function isVerified(User $user): bool
    {
        return $user->email_verified_at !== null
            && $user->ownedTeams()->where('personal_team', false)->whereHas('profile', fn ($q) => $q->whereNotNull('description')->where('description', '!=', ''))->exists();
    }

    /** Decision D4: verified, a platform opened, and a second sign of use (a colleague invited, or a platform switched on) in time. */
    public function isActivated(User $user, Referral $referral): bool
    {
        // The window only stops late activations from being credited; it never reverses anything.
        if ($user->platform_opened_at === null || now()->gt($referral->registered_at->copy()->addDays((int) config('referrals.activation_days')))) {
            return false;
        }

        $invited = OnboardingEvent::where('user_id', $user->id)->where('step', 'team_invited')->exists();
        $switchedOn = DB::table('platform_entitlements')
            ->whereIn('team_id', $user->ownedTeams()->where('personal_team', false)->pluck('id'))
            ->whereIn('status', ['active', 'trial'])->where('source', '!=', 'core')->exists();

        return $invited || $switchedOn;
    }

    public function reverse(Referral $referral, string $reason, ?User $actor = null): void
    {
        if (in_array($referral->state, [Referral::REVERSED, Referral::REFUSED], true)) {
            return;
        }

        DB::transaction(function () use ($referral, $reason, $actor) {
            $referral->forceFill(['state' => Referral::REVERSED, 'reason' => $reason, 'reversed_at' => now()])->save();
            $this->trail($referral->id, null, 'reversed', $reason, $actor?->id);
            $this->settleRewards($referral->referrer_team_id);
        });
    }

    /** Creates milestone rewards that are now earned, and takes back unsent ones that no longer are. Safe to run any number of times. */
    public function settleRewards(int $teamId): void
    {
        $activated = Referral::where('referrer_team_id', $teamId)->where('state', Referral::ACTIVATED)->count();

        foreach (ReferralTier::where('active', true)->orderBy('referrals_required')->get() as $tier) {
            $key = 'tier:'.$teamId.':'.$tier->id;
            $reward = ReferralReward::where('idempotency_key', $key)->first();
            $earned = $activated >= $tier->referrals_required;

            if ($earned && $reward === null) {
                try {
                    $reward = ReferralReward::create([
                        'referrer_team_id' => $teamId, 'tier_id' => $tier->id, 'points' => $tier->points, 'status' => 'held',
                        'idempotency_key' => $key, 'release_at' => now()->addDays((int) config('referrals.holding_days')),
                    ]);
                    $this->trail(null, $reward->id, 'reward_held', $tier->referrals_required.' activated referrals');
                } catch (UniqueConstraintViolationException) {
                    // a concurrent run made it; there is still only one
                }
            } elseif (! $earned && $reward !== null && in_array($reward->status, ['held', 'due'], true)) {
                $reward->forceFill(['status' => 'reversed', 'reversed_at' => now()])->save();
                $this->trail(null, $reward->id, 'reward_reversed', 'fewer activated referrals than the tier needs');
            }
        }
    }

    /** Held rewards whose holding period is over become due. Sending to the points ledger waits for the Dopemine contract. */
    public function release(): int
    {
        $n = 0;
        foreach (ReferralReward::where('status', 'held')->where('release_at', '<=', now())->get() as $reward) {
            $reward->forceFill(['status' => 'due', 'released_at' => now()])->save();
            $this->trail(null, $reward->id, 'reward_due');
            $n++;
        }

        return $n;
    }

    private function newCode(): string
    {
        $alphabet = (string) config('referrals.code_alphabet');
        $code = '';
        for ($i = 0; $i < (int) config('referrals.code_length'); $i++) {
            $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $code;
    }

    private function trail(?int $referralId, ?int $rewardId, string $type, ?string $reason = null, ?int $actorId = null): void
    {
        ReferralEvent::create(['referral_id' => $referralId, 'reward_id' => $rewardId, 'type' => $type, 'reason' => $reason, 'actor_id' => $actorId]);
    }
}
