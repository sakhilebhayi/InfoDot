<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The sign-in pages: a heading and a tab title each, and a password field that can be shown. */
class AuthPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_sign_in_page_has_one_heading_and_its_own_tab_title(): void
    {
        // R-67: the tab titles stay plain; the headings speak in InfoDot's voice.
        foreach (['/login' => ['Sign in', 'Welcome back'], '/register' => ['Create your account', 'Create your free InfoDot account'], '/forgot-password' => ['Reset your password', 'Reset your password']] as $path => [$title, $heading]) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, '<h1'), $path.' has exactly one h1');
            $this->assertStringContainsString('<h1 class="ix-auth-title">'.$heading.'</h1>', $html);
            $this->assertStringContainsString('<title>'.$title.' · InfoDot</title>', $html);
        }
    }

    public function test_infodots_own_sign_in_and_registration_say_what_the_account_opens(): void
    {
        $login = $this->get('/login')->assertOk()->getContent();
        $this->assertStringContainsString('class="ix-entry__panel"', $login);
        $this->assertStringContainsString('A better, more connected way to build a business.', $login);
        $this->assertStringContainsString('Every Dot tool your business uses, opened with this one sign-in.', $login);

        $register = $this->get('/register')->assertOk()->getContent();
        $this->assertStringContainsString('Your first step to a better-equipped business.', $register);
        $this->assertStringContainsString('Creating an account is free. You set up your company on the next step.', $register);
        $this->assertStringContainsString('aria-describedby="email-hint"', $register);

        // The panel is InfoDot's: the plainer ways in do not carry it.
        $this->assertStringNotContainsString('class="ix-entry__panel"', $this->get('/forgot-password')->getContent());
    }

    public function test_password_fields_can_be_shown_and_stay_plain_password_inputs_without_script(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertStringContainsString('class="ix-pw"', $html);
        $this->assertStringContainsString('aria-label="Show password"', $html);
        $this->assertMatchesRegularExpression('/<input[^>]*type="password"[^>]*name="password"/', $html);
        $this->assertStringContainsString('autocomplete="current-password"', $html);

        $this->assertSame(2, substr_count($this->get('/register')->getContent(), 'class="ix-pw"'), 'both register password fields');
    }
}
