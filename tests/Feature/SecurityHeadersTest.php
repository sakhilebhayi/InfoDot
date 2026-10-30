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

    public function test_the_policy_is_enforced_by_default_and_still_reports(): void
    {
        $r = $this->get('/login');

        $policy = (string) $r->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $policy);
        $this->assertStringContainsString('report-uri /csp-report', $policy, 'legacy reporting stays for older browsers');
        $this->assertStringContainsString('report-to csp', $policy, 'modern reporting is declared');
        $this->assertStringContainsString('csp="', (string) $r->headers->get('Reporting-Endpoints'), 'the reporting endpoint is named');
        $this->assertStringNotContainsString('unpkg.com', $policy, 'unused origins were dropped before enforcing');
        $this->assertStringNotContainsString('gravatar', $policy);
        $this->assertFalse($r->headers->has('Content-Security-Policy-Report-Only'));

        // The kill-switch drops back to report-only without a deploy; nothing is then enforced.
        config(['security.csp_report_only' => true]);
        $fallback = $this->get('/login');
        $this->assertStringContainsString("default-src 'self'", (string) $fallback->headers->get('Content-Security-Policy-Report-Only'));
        $this->assertFalse($fallback->headers->has('Content-Security-Policy'));
    }

    public function test_script_execution_is_nonce_based_with_no_unsafe_inline(): void
    {
        $r = $this->get('/login');
        $policy = (string) $r->headers->get('Content-Security-Policy');

        preg_match('/script-src ([^;]+);/', $policy, $m);
        $scriptSrc = $m[1] ?? '';

        $this->assertStringNotContainsString("'unsafe-inline'", $scriptSrc, 'inline scripts run by nonce only');
        $this->assertMatchesRegularExpression("/'nonce-[A-Za-z0-9+\/=]+'/", $scriptSrc);
        $this->assertStringContainsString("'unsafe-eval'", $scriptSrc, 'documented stack requirement: Livewire 3 bundles Alpine, whose evaluator needs it');
        $this->assertStringContainsString("script-src-attr 'none'", $policy, 'native on*= handlers are banned (all were converted to Alpine directives)');
        $this->assertStringContainsString("object-src 'none'", $policy);
        $this->assertStringContainsString("frame-src 'self'", $policy, 'PDF previews stay same-origin');

        // The page's own script tags carry the header's nonce, so they actually run under the policy.
        preg_match("/'nonce-([A-Za-z0-9+\/=]+)'/", $scriptSrc, $n);
        $this->assertStringContainsString('nonce="'.$n[1].'"', $r->getContent(), 'rendered scripts carry the same per-request nonce');
    }

    public function test_options_on_the_root_is_the_framework_answering_truthfully(): void
    {
        $r = $this->call('OPTIONS', '/');

        $r->assertOk();
        $this->assertSame('GET,HEAD', str_replace(' ', '', (string) $r->headers->get('Allow')), 'the Allow header matches the methods the route really supports');
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
