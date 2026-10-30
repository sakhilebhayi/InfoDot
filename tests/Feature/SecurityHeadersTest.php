<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Log;
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

    public function test_a_report_only_policy_is_sent_and_blocks_nothing(): void
    {
        $r = $this->get('/login');

        $this->assertStringContainsString("default-src 'self'", (string) $r->headers->get('Content-Security-Policy-Report-Only'));
        $this->assertStringContainsString('report-uri /csp-report', (string) $r->headers->get('Content-Security-Policy-Report-Only'));
        $this->assertFalse($r->headers->has('Content-Security-Policy'), 'report-only: nothing is enforced yet');

        config(['security.csp_report_only' => false]);
        $this->assertFalse($this->get('/login')->headers->has('Content-Security-Policy-Report-Only'));
    }

    public function test_violation_reports_are_accepted_without_a_session_and_keep_only_the_directive_and_host(): void
    {
        Log::spy();
        $body = json_encode(['csp-report' => ['effective-directive' => 'script-src-elem', 'blocked-uri' => 'https://evil.example/steal?token=SECRET123', 'document-uri' => 'https://infodot.co.za/x?code=SECRET456']]);

        $this->call('POST', '/csp-report', [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], $body)->assertNoContent();
        $this->call('POST', '/csp-report', [], [], [], ['CONTENT_TYPE' => 'application/csp-report'], $body)->assertNoContent();   // the same pair is logged once a day

        Log::shouldHaveReceived('info')->withArgs(fn ($m, $ctx = []) => $m === 'csp.violation' && $ctx === ['directive' => 'script-src-elem', 'blocked_host' => 'evil.example'])->once();
        $this->call('POST', '/csp-report', [], [], [], ['CONTENT_TYPE' => 'application/reports+json'], 'not json at all')->assertNoContent();
        $this->call('POST', '/csp-report', [], [], [], ['CONTENT_TYPE' => 'application/reports+json'], json_encode([['type' => 'csp-violation', 'body' => ['effectiveDirective' => 'img-src', 'blockedURL' => 'inline']]]))->assertNoContent();
    }
}
