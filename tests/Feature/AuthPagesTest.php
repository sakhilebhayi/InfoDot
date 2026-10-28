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
        foreach (['/login' => 'Sign in', '/register' => 'Create your account', '/forgot-password' => 'Reset your password'] as $path => $title) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, '<h1'), $path.' has exactly one h1');
            $this->assertStringContainsString('<h1 class="ix-auth-title">'.$title.'</h1>', $html);
            $this->assertStringContainsString('<title>'.$title.' · InfoDot</title>', $html);
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
