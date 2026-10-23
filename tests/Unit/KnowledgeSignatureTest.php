<?php

namespace Tests\Unit;

use App\Support\Knowledge\Signature;
use PHPUnit\Framework\TestCase;

/**
 * The signing scheme both sides of the Verified library must agree on. The expected values were produced with
 * `openssl dgst -sha256 -hmac`, not with this code, so another implementation can check itself against them.
 */
class KnowledgeSignatureTest extends TestCase
{
    public function test_a_get_signs_the_timestamp_and_a_dot_with_an_empty_body(): void
    {
        // printf '%s' '1700000000.' | openssl dgst -sha256 -hmac 'brain-secret'
        $this->assertSame('sha256=27bcf34443f3118001d2ae002bcb50ccf3bc286b5faea90e95fb95c550084394', Signature::sign('brain-secret', 1700000000, ''));
    }

    public function test_a_post_signs_the_raw_body_exactly_as_sent(): void
    {
        $body = '{"outcome":"adopted","actor_ref":"9d1c0b52-0a8c-4a5e-9f43-2b3c1d9e7a10"}';

        // printf '%s' '1700000000.{"outcome":...}' | openssl dgst -sha256 -hmac 'infodot-secret'
        $this->assertSame('sha256=d0054bae814867fe91888eaf35fb730647fc3b2d962902cedec2cc40509090e8', Signature::sign('infodot-secret', 1700000000, $body));
    }

    public function test_headers_carry_the_timestamp_and_the_prefixed_signature(): void
    {
        $this->assertSame(
            ['X-InfoDot-Timestamp' => '1700000000', 'X-InfoDot-Signature' => 'sha256=27bcf34443f3118001d2ae002bcb50ccf3bc286b5faea90e95fb95c550084394'],
            Signature::headers('brain-secret', '', 1700000000),
        );
    }

    public function test_valid_accepts_inside_the_window_and_refuses_outside_it(): void
    {
        $now = time();

        $this->assertTrue(Signature::valid('s', (string) $now, Signature::sign('s', $now), '', 300));
        $this->assertTrue(Signature::valid('s', (string) ($now - 299), Signature::sign('s', $now - 299), '', 300));
        $this->assertTrue(Signature::valid('s', (string) ($now + 299), Signature::sign('s', $now + 299), '', 300));
        $this->assertFalse(Signature::valid('s', (string) ($now - 305), Signature::sign('s', $now - 305), '', 300));
        $this->assertFalse(Signature::valid('s', (string) ($now + 305), Signature::sign('s', $now + 305), '', 300));
    }

    public function test_valid_refuses_a_wrong_secret_body_prefix_or_malformed_input(): void
    {
        $now = time();
        $good = Signature::sign('s', $now, 'body');

        $this->assertTrue(Signature::valid('s', (string) $now, $good, 'body', 300));
        $this->assertFalse(Signature::valid('other', (string) $now, $good, 'body', 300));
        $this->assertFalse(Signature::valid('s', (string) $now, $good, 'tampered', 300));
        $this->assertFalse(Signature::valid('s', (string) $now, substr($good, 7), 'body', 300), 'the sha256= prefix is part of the contract');
        $this->assertFalse(Signature::valid('s', 'yesterday', $good, 'body', 300));
        $this->assertFalse(Signature::valid('s', null, $good, 'body', 300));
        $this->assertFalse(Signature::valid('s', (string) $now, null, 'body', 300));
        $this->assertFalse(Signature::valid('', (string) $now, Signature::sign('', $now, 'body'), 'body', 300), 'an empty secret never validates');
    }
}
