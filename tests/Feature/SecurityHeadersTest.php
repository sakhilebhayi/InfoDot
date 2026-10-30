<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Baseline browser protections are on every response, and a route's own stricter header is kept. */
class SecurityHeadersTest extends TestCase
{
    public function test_every_response_carries_the_baseline_headers(): void
    {
        $r = $this->get('/login');

        $r->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'SAMEORIGIN')->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString('camera=(self)', (string) $r->headers->get('Permissions-Policy'));
        $this->get('/up')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/does-not-exist')->assertNotFound()->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_hsts_is_sent_on_https_only_and_can_be_switched_off(): void
    {
        $this->assertFalse($this->get('/login')->headers->has('Strict-Transport-Security'), 'plain http never gets HSTS');
        $this->assertSame('max-age=15552000', $this->get('https://localhost/login')->headers->get('Strict-Transport-Security'));

        config(['security.hsts_seconds' => 0]);
        $this->assertFalse($this->get('https://localhost/login')->headers->has('Strict-Transport-Security'));
    }

    public function test_a_stricter_header_set_by_the_route_is_not_overwritten(): void
    {
        Route::get('/__strict', fn () => response('x')->header('X-Frame-Options', 'DENY'));

        $this->get('/__strict')->assertHeader('X-Frame-Options', 'DENY');
    }
}
