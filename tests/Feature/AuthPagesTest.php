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

    public function test_infodots_own_sign_in_pages_are_split_with_a_photograph_and_what_the_account_opens(): void
    {
        // R-69: sign in, registration and the account pages are split pages: a photograph and InfoDot's message beside the form.
        $login = $this->get('/login')->assertOk()->getContent();
        $this->assertStringContainsString('class="ix-sa" data-panel="login"', $login);
        $this->assertStringContainsString('A better, more connected way to build your business.', $login);
        $this->assertStringContainsString('Every Dot tool your business uses, opened with this one sign-in.', $login);
        $this->assertStringNotContainsString('class="ix-band"', $login, 'the photograph replaces the ACCT band');

        $register = $this->get('/register')->assertOk()->getContent();
        $this->assertStringContainsString('data-panel="register"', $register);
        $this->assertStringContainsString('Your first step to a better-equipped business.', $register);
        $this->assertStringContainsString('Creating an account is free. You set up your company on the next step.', $register);
        $this->assertStringContainsString('aria-describedby="email-hint"', $register);

        $forgot = $this->get('/forgot-password')->assertOk()->getContent();
        $this->assertStringContainsString('data-panel="account"', $forgot);
        $this->assertStringContainsString('One account for the knowledge, tools and businesses around you.', $forgot);

        // Each kind has its own photograph on disk, wired in the panel (tests run without the Vite build, so the <img> itself is not drawn here).
        $panel = (string) file_get_contents(resource_path('views/components/auth/infodot-panel.blade.php'));
        foreach (['auth-cafe', 'auth-start-here', 'auth-market-hall'] as $photo) {
            $this->assertFileExists(resource_path('img/auth/'.$photo.'.jpg'));
            $this->assertStringContainsString("'img' => '{$photo}'", $panel);
        }
        $this->assertStringContainsString("'../img/auth/*.jpg'", (string) file_get_contents(resource_path('js/app.js')), 'the photographs are in the build');

        // Each page keeps exactly one form heading and the main landmark.
        foreach ([$login, $register, $forgot] as $html) {
            $this->assertSame(1, substr_count($html, '<h1'));
            $this->assertSame(1, substr_count($html, 'id="main"'));
        }
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
