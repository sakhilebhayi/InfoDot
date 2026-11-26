<?php

namespace Tests\Feature\Growth;

use App\Models\AcquisitionTouch;
use App\Models\Referral;
use App\Models\ReferralEvent;
use App\Models\ReferralReward;
use App\Models\ReferralTier;
use App\Models\Team;
use App\Models\User;
use App\Services\Growth\OnboardingEvents;
use App\Services\Growth\Referrals;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralsTest extends TestCase
{
    use RefreshDatabase;

    private function referrer(string $email = 'owner@acme-one.test'): Team
    {
        $user = User::factory()->create(['email' => $email, 'email_verified_at' => now()]);
        $team = $user->ownedTeams()->create(['name' => 'Referrer Ltd', 'personal_team' => false]);
        $team->profile()->create(['visibility' => 'private', 'description' => 'We trade.']);

        return $team;
    }

    private function arrive(string $code, string $email, ?\DateTimeInterface $at = null): User
    {
        $user = User::factory()->create(['email' => $email]);
        AcquisitionTouch::create(['user_id' => $user->id, 'channel' => 'referral', 'referral_code' => $code, 'touched_at' => $at ?? now(), 'created_at' => now()]);

        return $user;
    }

    private function activate(User $user): void
    {
        $user->forceFill(['email_verified_at' => now(), 'platform_opened_at' => now()])->save();
        $team = $user->ownedTeams()->create(['name' => 'Referred Ltd', 'personal_team' => false]);
        $team->profile()->create(['visibility' => 'private', 'description' => 'We sell.']);
        app(OnboardingEvents::class)->record($user, 'team_invited', $team);
    }

    public function test_a_code_is_issued_once_per_business(): void
    {
        $team = $this->referrer();
        $a = app(Referrals::class)->codeFor($team);
        $b = app(Referrals::class)->codeFor($team);

        $this->assertSame($a->code, $b->code);
        $this->assertMatchesRegularExpression('/^[A-Z2-9]{8}$/', $a->code);
    }

    public function test_full_path_registered_to_activated_to_rewarded(): void
    {
        $service = app(Referrals::class);
        $team = $this->referrer();
        $code = $service->codeFor($team)->code;
        ReferralTier::query()->update(['active' => false]);
        $tier = ReferralTier::where('referrals_required', 5)->first();
        $tier->update(['active' => true, 'referrals_required' => 1]);

        $user = $this->arrive($code, 'new@other.test');
        $referral = $service->attribute($user);
        $this->assertSame(Referral::REGISTERED, $referral->state);

        $this->activate($user);
        $service->advance($referral->fresh());
        $this->assertSame(Referral::ACTIVATED, $referral->fresh()->state);

        $service->settleRewards($team->id);
        $service->settleRewards($team->id);
        $this->assertSame(1, ReferralReward::count(), 'a retry never repeats the reward');
        $this->assertSame('held', ReferralReward::first()->status);

        $this->assertSame(0, $service->release());
        $this->travel(31)->days();
        $this->assertSame(1, $service->release());
        $this->assertSame(0, $service->release());
        $this->assertSame('due', ReferralReward::first()->status);
    }

    public function test_self_referral_same_domain_and_duplicates_are_refused(): void
    {
        $service = app(Referrals::class);
        $team = $this->referrer('owner@acme-one.test');
        $code = $service->codeFor($team)->code;

        $self = $this->arrive($code, 'someone@gmail.com');
        $self->forceFill(['email' => 'OWNER@acme-one.test'])->saveQuietly();
        $this->assertSame('self referral', $service->attribute($self)->reason);

        $colleague = $this->arrive($code, 'colleague@acme-one.test');
        $this->assertSame('same email domain', $service->attribute($colleague)->reason);

        $first = $this->arrive($code, 'one@gmail.com');
        $this->assertSame(Referral::REGISTERED, $service->attribute($first)->state);
        $again = $service->attribute($first);
        $this->assertSame(1, Referral::where('referred_user_id', $first->id)->count());
        $this->assertSame(Referral::REGISTERED, $again->state);
    }

    public function test_arrival_outside_the_window_and_unknown_codes_are_not_credited(): void
    {
        $service = app(Referrals::class);
        $code = $service->codeFor($this->referrer())->code;

        $late = $this->arrive($code, 'late@other.test', now()->subDays(40));
        $this->assertSame('outside the attribution window', $service->attribute($late)->reason);

        $unknown = $this->arrive('NOSUCHCD', 'x@other.test');
        $this->assertNull($service->attribute($unknown));
    }

    public function test_daily_cap_is_enforced(): void
    {
        config(['referrals.daily_cap_per_code' => 1]);
        $service = app(Referrals::class);
        $code = $service->codeFor($this->referrer())->code;

        $this->assertSame(Referral::REGISTERED, $service->attribute($this->arrive($code, 'a@gmail.com'))->state);
        $this->assertSame('daily limit for this code', $service->attribute($this->arrive($code, 'b@gmail.com'))->reason);
    }

    public function test_reversal_takes_back_an_unsent_reward(): void
    {
        $service = app(Referrals::class);
        $team = $this->referrer();
        $code = $service->codeFor($team)->code;
        ReferralTier::query()->update(['active' => false]);
        ReferralTier::where('referrals_required', 5)->first()->update(['active' => true, 'referrals_required' => 1]);

        $user = $this->arrive($code, 'new@other.test');
        $referral = $service->attribute($user);
        $this->activate($user);
        $service->advance($referral->fresh());
        $service->settleRewards($team->id);

        $service->reverse($referral->fresh(), 'refund inside holding period');
        $this->assertSame(Referral::REVERSED, $referral->fresh()->state);
        $this->assertSame('reversed', ReferralReward::first()->status);

        $service->reverse($referral->fresh(), 'again');
        $this->assertSame(1, ReferralEvent::where('type', 'reversed')->count());
    }

    public function test_command_advances_and_is_idempotent(): void
    {
        $service = app(Referrals::class);
        $team = $this->referrer();
        $code = $service->codeFor($team)->code;
        $user = $this->arrive($code, 'new@other.test');
        $service->attribute($user);
        $this->activate($user);

        $this->artisan('referrals:advance')->assertSuccessful();
        $this->artisan('referrals:advance')->assertSuccessful();

        $this->assertSame(Referral::ACTIVATED, Referral::first()->state);
    }

    public function test_owner_page_shows_link_and_counts_only(): void
    {
        $team = $this->referrer();
        $owner = $team->owner;
        $this->get('/referrals')->assertRedirect();

        $this->actingAs($owner)->get('/referrals')->assertOk()->assertSee('?ref=', false)->assertSee('Active businesses');
    }

    public function test_admin_can_change_tiers_and_it_is_logged(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true, 'two_factor_secret' => encrypt('test-secret'), 'two_factor_confirmed_at' => now(), 'email_verified_at' => now()]);
        $tier = ReferralTier::where('referrals_required', 5)->first();

        $this->actingAs($admin)->get('/admin/growth/referrals')->assertOk();
        $this->actingAs($admin)->post('/admin/growth/tiers', ['tiers' => [$tier->id => ['points' => 150, 'active' => 1]]])->assertRedirect();

        $this->assertSame(150, $tier->fresh()->points);
        $this->assertSame(1, ReferralEvent::where('type', 'tier_changed')->count());
    }
}
