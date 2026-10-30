<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** R-17 §13: the public pages are discoverable, consistently described, and nothing private leaks into the map. */
class SeoTest extends TestCase
{
    use RefreshDatabase;

    private const PUBLIC_PAGES = ['/', '/about', '/features', '/faqs', '/contact', '/complains', '/terms', '/policy', '/cookies', '/security'];

    public function test_robots_states_an_explicit_crawler_policy(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        $this->assertStringContainsString('Sitemap: https://infodot.co.za/sitemap.xml', $robots);
        $this->assertStringContainsString("User-agent: GPTBot\nDisallow: /", $robots, 'training crawlers are blocked by default');
        $this->assertStringContainsString('Disallow: /questions', $robots);
        $this->assertStringContainsString('Disallow: /moderation', $robots);
        $this->assertStringNotContainsString('Disallow: /about', $robots, 'public pages stay crawlable');
    }

    public function test_the_sitemap_lists_public_pages_and_nothing_private(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml, 'the sitemap is well-formed XML');

        $urls = array_map(fn ($url) => (string) $url->loc, iterator_to_array($xml->url, false));
        $this->assertContains(route('about'), $urls);
        $this->assertContains(route('faqs'), $urls);
        $this->assertContains(route('policy'), $urls);

        foreach (['/questions', '/home', '/moderation', '/library', '/login'] as $private) {
            $this->assertEmpty(array_filter($urls, fn ($url) => str_contains($url, $private)), $private.' must not be in the sitemap');
        }
    }

    public function test_every_public_page_carries_canonical_description_and_social_metadata(): void
    {
        $titles = [];

        foreach (self::PUBLIC_PAGES as $page) {
            $html = $this->get($page)->assertOk()->getContent();

            $this->assertStringContainsString('<link rel="canonical" href="', $html, $page.' has a canonical');
            $this->assertStringContainsString('<meta name="description" content="', $html, $page.' has a description');
            $this->assertStringContainsString('og:title', $html, $page.' has social metadata');

            preg_match('/<title>([^<]+)<\/title>/', $html, $m);
            $titles[$page] = $m[1] ?? '';
            $this->assertNotSame('', $titles[$page], $page.' has a title');
        }

        $this->assertSame(count($titles), count(array_unique($titles)), 'every public page has a unique title');
    }

    public function test_the_faq_page_structured_data_matches_the_visible_content(): void
    {
        $html = $this->get('/faqs')->assertOk()->getContent();

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $m);
        $data = json_decode($m[1] ?? '', true);

        $this->assertSame('FAQPage', $data['@type'] ?? null);
        $this->assertGreaterThanOrEqual(8, count($data['mainEntity']));

        foreach ($data['mainEntity'] as $entity) {
            $this->assertStringContainsString(e($entity['name']), $html, 'every structured question is visible on the page');
        }
    }

    public function test_the_home_page_declares_the_organisation(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $m);
        $data = json_decode($m[1] ?? '', true);

        $this->assertSame('Organization', $data['@graph'][0]['@type'] ?? null);
        $this->assertSame('InfoDot', $data['@graph'][0]['name'] ?? null);
    }

    public function test_jetstreams_duplicate_legal_urls_redirect_to_the_canonical_pages(): void
    {
        $this->get('/terms-of-service')->assertMovedPermanently()->assertRedirect('/terms');
        $this->get('/privacy-policy')->assertMovedPermanently()->assertRedirect('/policy');
    }

    public function test_security_txt_is_published_valid_and_not_near_expiry(): void
    {
        $response = $this->get('/.well-known/security.txt');

        $response->assertOk();
        $body = $response->getContent();
        $this->assertStringContainsString('Contact: mailto:privacy@infodot.co.za', $body);
        $this->assertStringContainsString('Policy: https://infodot.co.za/security', $body);
        $this->assertStringContainsString('Canonical: https://infodot.co.za/.well-known/security.txt', $body);

        // The Policy line must point at a live page that carries the disclosure essentials.
        $policyPage = $this->get('/security')->assertOk()->getContent();
        $this->assertStringContainsString('privacy@infodot.co.za', $policyPage);
        $this->assertStringContainsString('Safe harbor', $policyPage);
        $this->assertStringContainsString('Scope', $policyPage);

        preg_match('/^Expires:\s*(\S+)/m', $body, $m);
        $expires = Carbon::parse($m[1] ?? 'now');
        $this->assertTrue($expires->greaterThan(now()->addDays(30)), 'security.txt must carry a comfortably future Expires (ops:check alerts at 30 days)');
        $this->assertTrue($expires->lessThan(now()->addDays(400)), 'RFC 9116 recommends expiry within a year');
    }

    public function test_password_hashing_defaults_to_the_current_cost(): void
    {
        // The test environment pins BCRYPT_ROUNDS=4 for speed, so the DEFAULT is pinned at the source:
        // without an env override, production hashes at cost 12 (Laravel's current default).
        $this->assertStringContainsString("env('BCRYPT_ROUNDS', 12)", file_get_contents(config_path('hashing.php')));
    }
}
