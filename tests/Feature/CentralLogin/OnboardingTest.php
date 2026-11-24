<?php

namespace Tests\Feature\CentralLogin;

use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

/**
 * R-54: register, verify, set up a business, continue the platform's request. NO_PERSONAL_ACCESS for a person with no
 * business is a step with a clear message, not a dead refusal; every other refusal is exactly what it was.
 */
class OnboardingTest extends CentralLoginTestCase
{
    public function test_the_whole_journey_from_a_platform_register_verify_business_and_back(): void
    {
        Notification::fake();

        // 1. The platform sends the browser; the visitor has no account and goes to register.
        $f = $this->flowId();
        $this->get('/register?f='.$f)->assertOk()->assertViewHas('platformBranding', fn ($b) => $b->slug === 'central');

        // 2. Registration (existing rules) signs them in and returns to the pending request, which asks them to verify.
        $password = 'Pw-'.bin2hex(random_bytes(10)).'-Aa1';
        $this->post('/register', ['f' => $f, 'name' => 'Thandi Dlamini', 'email' => 'thandi@example.test', 'password' => $password, 'password_confirmation' => $password, 'terms' => 'on'])
            ->assertRedirect();
        $user = User::where('email', 'thandi@example.test')->firstOrFail();
        $this->assertNull($user->email_verified_at);
        $this->assertSame(1, $user->allTeams()->count());
        $this->assertTrue((bool) $user->allTeams()->first()->personal_team);

        $this->get($this->authorizeUrl())->assertRedirect(route('verification.notice'));
        $this->get('/email/verify')->assertOk()->assertViewHas('platformBranding', fn ($b) => $b->slug === 'central');
        Notification::assertSentTo($user, VerifyEmail::class);

        // 3. They confirm the address: the platform's request continues (not the Home page).
        $link = URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), ['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())]);
        $this->get($link)->assertRedirect()->assertRedirectContains('/oauth/authorize?');
        $this->assertNotNull($user->fresh()->email_verified_at);

        // 4. The platform is for businesses and they have none: the step, not a refusal.
        $this->get($this->authorizeUrl())->assertRedirect(route('onboarding.business'));
        $this->get('/onboarding/business')->assertOk()->assertSee('Set up your business')->assertSee('Dot.Central is for businesses');

        // 5. They name the business, choosing who can see it, and continue.
        $this->post('/onboarding/business', ['name' => 'Dlamini Freight', 'visibility' => 'private'])
            ->assertRedirectContains('/oauth/authorize?');

        $business = Team::where('name', 'Dlamini Freight')->firstOrFail();
        $this->assertFalse((bool) $business->personal_team);
        $this->assertSame($user->id, $business->user_id);
        $this->assertSame($business->id, $user->fresh()->current_team_id);
        $this->assertSame('private', $business->profile->visibility);

        // 6. The platform's own entitlement check then runs exactly as before: nobody has switched Central on yet.
        $this->get($this->authorizeUrl())->assertForbidden()->assertSee('has not switched on');
    }

    public function test_a_second_submission_creates_nothing_twice(): void
    {
        $user = $this->newcomer();
        $this->actingAs($user)->get($this->authorizeUrl());

        $this->post('/onboarding/business', ['name' => 'Once Ltd', 'visibility' => 'public'])->assertRedirect();
        $this->post('/onboarding/business', ['name' => 'Twice Ltd', 'visibility' => 'public'])->assertRedirect();

        $this->assertSame(1, Team::where('user_id', $user->id)->where('personal_team', false)->count());
        $this->assertDatabaseMissing('teams', ['name' => 'Twice Ltd']);
    }

    public function test_the_existing_validation_applies_and_nothing_is_chosen_for_the_person(): void
    {
        $user = $this->newcomer();

        $this->actingAs($user)->from('/onboarding/business')->post('/onboarding/business', ['name' => 'No Choice Ltd'])
            ->assertRedirect('/onboarding/business')->assertSessionHasErrors('visibility');
        $this->post('/onboarding/business', ['name' => '', 'visibility' => 'public'])->assertSessionHasErrors('name');
        $this->post('/onboarding/business', ['name' => 'Odd Ltd', 'visibility' => 'public', 'industry' => 'not-an-industry'])->assertSessionHasErrors('industry');

        $this->assertSame(0, Team::where('user_id', $user->id)->where('personal_team', false)->count());
    }

    public function test_a_person_with_a_business_is_not_sent_to_the_step_and_keeps_the_ordinary_refusal(): void
    {
        $user = $this->newcomer();
        $company = Team::factory()->create(['user_id' => $user->id, 'personal_team' => false]);
        $user->forceFill(['current_team_id' => $user->personalTeam()->id])->save();

        // Their current workspace is the personal one, so the platform refuses with the existing page (switch company), no step.
        $this->actingAs($user)->get($this->authorizeUrl())->assertForbidden()->assertSee('is for companies');
        $this->flushSession();
        $this->actingAs($user)->get('/onboarding/business')->assertRedirect('/home');
        $this->post('/onboarding/business', ['name' => 'Another', 'visibility' => 'public'])->assertRedirect('/home');
        $this->assertSame(1, Team::where('user_id', $user->id)->where('personal_team', false)->count());
        $this->assertNotNull($company);
    }

    public function test_the_step_is_for_signed_in_verified_people_only(): void
    {
        $this->get('/onboarding/business')->assertRedirect('/login');
        $this->post('/onboarding/business', ['name' => 'X', 'visibility' => 'public'])->assertRedirect('/login');

        $this->actingAs($this->newcomer(verified: false))->get('/onboarding/business')->assertRedirect(route('verification.notice'));
    }

    public function test_without_a_pending_request_the_step_still_works_and_lands_on_home(): void
    {
        $user = $this->newcomer();

        $this->actingAs($user)->get('/onboarding/business')->assertOk()->assertDontSee('is for businesses');
        $this->post('/onboarding/business', ['name' => 'Standalone Ltd', 'visibility' => 'members'])->assertRedirect('/home');
    }

    public function test_one_person_cannot_create_a_business_for_another(): void
    {
        $alice = $this->newcomer();
        $bob = $this->newcomer();

        $this->actingAs($alice)->post('/onboarding/business', ['name' => 'Alice Co', 'visibility' => 'public', 'user_id' => $bob->id, 'personal_team' => 1]);

        $team = Team::where('name', 'Alice Co')->firstOrFail();
        $this->assertSame($alice->id, $team->user_id);
        $this->assertFalse((bool) $team->personal_team);
        $this->assertSame(0, Team::where('user_id', $bob->id)->where('personal_team', false)->count());
        $this->assertNotSame($team->id, $bob->fresh()->current_team_id);
    }

    public function test_a_tampered_pending_address_cannot_steer_the_continue_redirect_off_site(): void
    {
        $user = $this->newcomer();

        $response = $this->actingAs($user)->withSession(['sso.pending_authorize' => 'https://evil.example/oauth/authorize?client_id=x', 'url.intended' => 'https://evil.example/steal'])
            ->post('/onboarding/business', ['name' => 'Safe Ltd', 'visibility' => 'public']);

        $response->assertRedirect('/home');
    }

    public function test_other_refusals_are_untouched(): void
    {
        $user = $this->newcomer();
        $this->companyWith($user, 'hr'); // a business with some other platform; Central is not switched on

        $this->actingAs($user)->get($this->authorizeUrl())->assertForbidden()->assertSee('has not switched on');
    }
}
