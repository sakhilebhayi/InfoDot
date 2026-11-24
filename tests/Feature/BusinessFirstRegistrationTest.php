<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/** R-62 phase 1: the register form is about the person's own login, and the company comes next. */
class BusinessFirstRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function register(array $extra = []): TestResponse
    {
        return $this->from('/register')->post('/register', $extra + ['email' => 'thandi@example.test', 'password' => 'A-long-Passphrase-2026!', 'password_confirmation' => 'A-long-Passphrase-2026!', 'terms' => 'on']);
    }

    public function test_the_page_asks_for_first_and_last_name_not_a_single_name_or_a_username(): void
    {
        $this->get('/register')->assertOk()
            ->assertSee('Create your InfoDot account to start and manage your business.')
            ->assertSee('name="first_name"', false)->assertSee('name="last_name"', false)
            ->assertDontSee('name="name"', false)->assertDontSee('name="username"', false);
    }

    public function test_first_and_last_name_are_stored_joined_in_the_one_name_column(): void
    {
        $this->register(['first_name' => '  Thandi ', 'last_name' => 'Dlamini'])->assertSessionHasNoErrors();

        $this->assertSame('Thandi Dlamini', User::where('email', 'thandi@example.test')->value('name'));
    }

    public function test_a_caller_that_still_sends_a_single_name_works_as_before(): void
    {
        $this->register(['name' => 'Thandi Dlamini'])->assertSessionHasNoErrors();

        $this->assertSame('Thandi Dlamini', User::where('email', 'thandi@example.test')->value('name'));
    }

    public function test_a_missing_name_is_refused_and_creates_nothing(): void
    {
        $this->register(['first_name' => 'Thandi'])->assertSessionHasErrors();
        $this->register([])->assertSessionHasErrors('name');

        $this->assertSame(0, User::count());
    }

    public function test_the_login_page_says_the_business_starts_here(): void
    {
        $this->get('/login')->assertOk()->assertSee('Your business starts here. Sign in to your InfoDot workspace.');
    }
}
