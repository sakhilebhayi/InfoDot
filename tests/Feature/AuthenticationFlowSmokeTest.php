<?php

namespace Tests\Feature;

use App\Models\Solutions;
use App\Models\Team;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationFlowSmokeTest extends TestCase
{
    use RefreshDatabase;

    private function createVerifiedUserWithCurrentTeam(): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $team = Team::factory()->create([
            'user_id' => $user->id,
            'name' => $user->name."'s Team",
            'personal_team' => true,
        ]);

        $user->forceFill([
            'current_team_id' => $team->id,
        ])->save();

        return $user;
    }

    public function test_guest_public_pages_expose_auth_entry_points(): void
    {
        foreach (['/', '/contact'] as $path) {
            $response = $this->get($path);

            $response->assertOk();
            $response->assertSee(route('login'), false);
            $response->assertSee(route('register'), false);
        }
    }

    public function test_auth_pages_render_the_existing_platform_designs(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('Sign in with your credentials')
            ->assertSee(route('register'), false);

        $this->get('/register')
            ->assertOk()
            ->assertSee('Already registered?')
            ->assertSee(route('login'), false);
    }

    public function test_guest_marketing_pages_render_shared_navigation(): void
    {
        $pages = [
            '/about' => 'About',
            '/faqs' => 'Frequently Asked Questions',
            '/features' => 'Features',
            '/terms' => 'Terms &amp; Conditions',
            '/complains' => 'Complains',
        ];

        foreach ($pages as $path => $heading) {
            $response = $this->get($path);

            $response->assertOk();
            $response->assertSee($heading, false);
            $response->assertSee(route('about'), false);
            $response->assertSee(route('contact'), false);
            $response->assertSee(route('terms'), false);
        }
    }

    public function test_guest_search_results_call_to_action_uses_login_route(): void
    {
        $owner = User::factory()->create();

        Solutions::create([
            'user_id' => $owner->id,
            'solution_title' => 'Register your business online',
            'solution_description' => 'Complete online registration quickly.',
            'tags' => 'registration, business',
            'duration' => 3,
            'duration_type' => 'days',
            'steps' => 3,
        ]);

        $response = $this->get('/solution-results?search=Register');

        $response->assertOk();
        $response->assertSee('Sign in to view');
        $response->assertSee(route('login'), false);
    }

    public function test_guest_is_redirected_from_profile_pages(): void
    {
        $user = $this->createVerifiedUserWithCurrentTeam();

        $this->get(route('profile.show', ['id' => $user->id]))
            ->assertRedirect(route('login'));

        $this->get(route('profile.edit'))
            ->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_access_profile_pages(): void
    {
        $user = $this->createVerifiedUserWithCurrentTeam();

        $this->actingAs($user)
            ->get(route('profile.show', ['id' => $user->id]))
            ->assertOk()
            ->assertSee($user->name);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk();
    }

    public function test_user_can_register_and_then_log_in_again(): void
    {
        $registerResponse = $this->post('/register', [
            'name' => 'Smoke Test User',
            'email' => 'smoke@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'terms' => 'on',
        ]);

        $this->assertAuthenticated();
        $registerResponse->assertRedirect(RouteServiceProvider::HOME);

        $this->post('/logout');

        $this->assertGuest();

        $loginResponse = $this->post('/login', [
            'email' => 'smoke@example.com',
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $loginResponse->assertRedirect(RouteServiceProvider::HOME);
    }

    public function test_authenticated_user_can_log_out_to_public_entry_page(): void
    {
        $user = $this->createVerifiedUserWithCurrentTeam();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');

        $this->get(route('questions'))
            ->assertRedirect(route('login'));
    }
}
