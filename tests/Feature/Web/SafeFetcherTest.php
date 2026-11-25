<?php

namespace Tests\Feature\Web;

use App\Services\Web\FetchFailed;
use App\Services\Web\Robots;
use App\Services\Web\SafeFetcher;
use App\Services\Web\UnsafeUrl;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** R-62 phase 3: the fetcher must never reach InfoDot's own network, whatever address it is given or redirected to. */
class SafeFetcherTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        SafeFetcher::$resolver = fn (string $host) => match ($host) {
            'good.example.co.za', 'www.good.example.co.za' => ['203.0.114.10'],
            'v6.example.co.za' => ['2606:4700:4700::1111'],
            'mixed.example.co.za' => ['203.0.114.10', '10.0.0.5'],
            'rebind.example.co.za' => ['169.254.169.254'],
            'private.example.co.za' => ['192.168.1.20'],
            'mapped.example.co.za' => ['::ffff:10.1.2.3'],
            'nowhere.example.co.za' => [],
            default => ['203.0.114.10'],
        };
        Cache::flush();
    }

    protected function tearDown(): void
    {
        SafeFetcher::$resolver = null;
        parent::tearDown();
    }

    public static function privateAddresses(): array
    {
        return [
            'loopback' => ['127.0.0.1'], 'loopback range' => ['127.9.9.9'], 'this network' => ['0.0.0.0'], 'private 10' => ['10.0.0.1'],
            'private 172' => ['172.16.5.5'], 'private 192' => ['192.168.0.1'], 'link-local / cloud metadata' => ['169.254.169.254'],
            'carrier-grade NAT' => ['100.64.0.1'], 'multicast' => ['224.0.0.1'], 'reserved' => ['240.0.0.1'], 'broadcast' => ['255.255.255.255'],
            'documentation' => ['192.0.2.1'], 'benchmarking' => ['198.18.0.1'],
            'ipv6 loopback' => ['::1'], 'ipv6 unspecified' => ['::'], 'ipv6 unique local' => ['fc00::1'], 'ipv6 unique local fd' => ['fd12:3456::1'],
            'ipv6 link-local' => ['fe80::1'], 'ipv6 multicast' => ['ff02::1'], 'ipv4-mapped private' => ['::ffff:10.0.0.1'], 'ipv4-mapped loopback' => ['::ffff:127.0.0.1'],
            'nat64' => ['64:ff9b::a00:1'], '6to4' => ['2002:c0a8:0101::1'], 'teredo' => ['2001:0:4136:e378:8000:63bf:3fff:fdd2'], 'not an address' => ['not-an-ip'],
        ];
    }

    #[DataProvider('privateAddresses')]
    public function test_private_and_reserved_addresses_are_never_public(string $ip): void
    {
        $this->assertFalse(SafeFetcher::isPublicIp($ip), $ip);
    }

    public function test_public_addresses_are_public(): void
    {
        foreach (['8.8.8.8', '203.0.114.10', '1.1.1.1', '2606:4700:4700::1111', '::ffff:8.8.8.8'] as $ip) {
            $this->assertTrue(SafeFetcher::isPublicIp($ip), $ip);
        }
    }

    public static function unsafeUrls(): array
    {
        return [
            'file scheme' => ['file:///etc/passwd'], 'ftp' => ['ftp://good.example.co.za/'], 'gopher' => ['gopher://good.example.co.za/'], 'javascript' => ['javascript:alert(1)'],
            'credentials' => ['https://user:pass@good.example.co.za/'], 'user only' => ['https://admin@good.example.co.za/'],
            'odd port' => ['https://good.example.co.za:8080/'], 'ssh port' => ['http://good.example.co.za:22/'],
            'loopback literal' => ['http://127.0.0.1/'], 'loopback ipv6' => ['http://[::1]/'], 'metadata literal' => ['http://169.254.169.254/latest/meta-data/'],
            'private literal' => ['http://10.0.0.1/'], 'decimal ip' => ['http://2130706433/'], 'hex ip' => ['http://0x7f000001/'], 'octal ip' => ['http://0177.0.0.1/'],
            'localhost' => ['http://localhost/'], 'dot local' => ['http://printer.local/'], 'internal' => ['http://db.internal/'], 'single label' => ['http://intranet/'],
            'name to private' => ['https://private.example.co.za/'], 'name to metadata' => ['https://rebind.example.co.za/'],
            'name to mixed public and private' => ['https://mixed.example.co.za/'], 'name to mapped private' => ['https://mapped.example.co.za/'],
            'name that does not resolve' => ['https://nowhere.example.co.za/'], 'empty' => [''], 'no host' => ['https:///path'], 'not a url' => ['hello world'],
            'too long' => ['https://good.example.co.za/'.str_repeat('a', 2100)],
        ];
    }

    #[DataProvider('unsafeUrls')]
    public function test_unsafe_addresses_are_refused_before_any_request_is_made(string $url): void
    {
        Http::fake();

        try {
            (new SafeFetcher)->get($url);
            $this->fail('An unsafe address was fetched: '.$url);
        } catch (UnsafeUrl) {
            $this->assertTrue(true);
        }

        Http::assertNothingSent();
    }

    public function test_a_public_page_is_read_and_the_connection_is_pinned_to_the_checked_address(): void
    {
        Http::fake(['good.example.co.za/*' => Http::response('<html><title>Hello</title></html>', 200, ['Content-Type' => 'text/html; charset=utf-8'])]);

        $page = (new SafeFetcher)->get('http://Good.Example.co.za/');

        $this->assertSame(200, $page['status']);
        $this->assertStringContainsString('Hello', $page['body']);
        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'good.example.co.za') && str_contains($request->header('User-Agent')[0], 'InfoDotBot');
        });
    }

    public function test_every_redirect_is_vetted_again_and_a_redirect_to_a_private_address_is_refused(): void
    {
        Http::fake([
            'good.example.co.za/start' => Http::response('', 302, ['Location' => 'http://169.254.169.254/latest/meta-data/']),
            'good.example.co.za/dns' => Http::response('', 301, ['Location' => 'https://private.example.co.za/admin']),
            'good.example.co.za/scheme' => Http::response('', 302, ['Location' => 'file:///etc/passwd']),
            'good.example.co.za/port' => Http::response('', 302, ['Location' => 'http://good.example.co.za:6379/']),
        ]);

        foreach (['start', 'dns', 'scheme', 'port'] as $path) {
            try {
                (new SafeFetcher)->get('https://good.example.co.za/'.$path);
                $this->fail('A redirect to an unsafe place was followed: '.$path);
            } catch (UnsafeUrl) {
                $this->assertTrue(true);
            }
        }

        Http::assertSentCount(4);
    }

    public function test_a_safe_redirect_is_followed_but_only_so_many_times(): void
    {
        Http::fake([
            'good.example.co.za/a' => Http::response('', 301, ['Location' => 'https://www.good.example.co.za/b']),
            'www.good.example.co.za/b' => Http::response('<p>ok</p>', 200, ['Content-Type' => 'text/html']),
            'good.example.co.za/loop' => Http::response('', 302, ['Location' => '/loop']),
        ]);

        $this->assertSame('https://www.good.example.co.za/b', (new SafeFetcher)->get('https://good.example.co.za/a')['url']);

        $this->expectException(FetchFailed::class);
        (new SafeFetcher)->get('https://good.example.co.za/loop');
    }

    public function test_only_an_ok_answer_of_an_allowed_kind_and_size_is_read(): void
    {
        Http::fake([
            'good.example.co.za/missing' => Http::response('nope', 404, ['Content-Type' => 'text/html']),
            'good.example.co.za/error' => Http::response('boom', 500, ['Content-Type' => 'text/html']),
            'good.example.co.za/file.pdf' => Http::response('%PDF-1.4', 200, ['Content-Type' => 'application/pdf']),
            'good.example.co.za/json' => Http::response('{}', 200, ['Content-Type' => 'application/json']),
            'good.example.co.za/huge' => Http::response(str_repeat('a', 3000), 200, ['Content-Type' => 'text/html']),
            'good.example.co.za/ok' => Http::response('<p>fine</p>', 200, ['Content-Type' => 'text/html']),
        ]);

        foreach (['missing' => 404, 'error' => 500, 'file.pdf' => 200, 'json' => 200] as $path => $status) {
            try {
                (new SafeFetcher)->get('https://good.example.co.za/'.$path);
                $this->fail("Read /$path");
            } catch (FetchFailed $e) {
                $this->assertSame($status, $e->status);
            }
        }

        try {
            (new SafeFetcher(maxBytes: 1000))->get('https://good.example.co.za/huge');
            $this->fail('A page over the size limit was read.');
        } catch (FetchFailed $e) {
            $this->assertStringContainsString('too large', $e->getMessage());
        }

        $this->assertStringContainsString('fine', (new SafeFetcher(maxBytes: 1000))->get('https://good.example.co.za/ok')['body']);
    }

    public function test_robots_rules_are_read_and_respected(): void
    {
        $rules = Robots::parse("User-agent: *\nDisallow: /private\nAllow: /private/open\nDisallow: /*.pdf$\n\nUser-agent: InfoDotBot\nDisallow: /nobots\n");

        $this->assertSame(['/nobots'], $rules['disallow'], 'the group for our own name wins over *');
        $general = Robots::parse("User-agent: *\nDisallow: /private\nAllow: /private/open\nDisallow: /*.pdf$\n");
        $this->assertFalse(Robots::decide($general, '/private/data'));
        $this->assertTrue(Robots::decide($general, '/private/open/page'), 'the longer Allow wins');
        $this->assertFalse(Robots::decide($general, '/files/brochure.pdf'));
        $this->assertTrue(Robots::decide($general, '/about'));
        $this->assertFalse(Robots::decide(Robots::parse("User-agent: *\nDisallow: /\n"), '/'));
        $this->assertTrue(Robots::decide(Robots::parse("User-agent: *\nDisallow:\n"), '/anything'), 'an empty Disallow allows everything');
    }

    public function test_robots_policy_for_missing_unreachable_and_blocking_files(): void
    {
        Http::fake([
            'good.example.co.za/robots.txt' => Http::response("User-agent: *\nDisallow: /\n", 200, ['Content-Type' => 'text/plain']),
            'nobots.example.co.za/robots.txt' => Http::response('Not found', 404, ['Content-Type' => 'text/html']),
            'down.example.co.za/robots.txt' => Http::response('Error', 503, ['Content-Type' => 'text/html']),
        ]);
        $robots = new Robots(new SafeFetcher);

        $this->assertFalse($robots->allows('https://good.example.co.za/'));
        $this->assertTrue($robots->allows('https://nobots.example.co.za/about'), 'no robots.txt means nothing is forbidden');
        $this->assertFalse($robots->allows('https://down.example.co.za/'), 'a site that cannot answer has not said we may');
    }

    public function test_connection_options_pin_the_checked_address_with_no_stream_and_no_proxy(): void
    {
        $o = app(SafeFetcher::class)->requestOptions('good.example.co.za', 443, '203.0.114.10', 10);
        $this->assertArrayNotHasKey('stream', $o);
        $this->assertSame('', $o['proxy']);
        $this->assertSame(['good.example.co.za:443:203.0.114.10'], $o['curl'][CURLOPT_RESOLVE]);
        $this->assertSame('', $o['curl'][CURLOPT_PROXY]);
        $this->assertFalse($o['allow_redirects']);

        $o6 = app(SafeFetcher::class)->requestOptions('v6.example.co.za', 443, '2606:4700:4700::1111', 10);
        $this->assertSame(['v6.example.co.za:443:2606:4700:4700::1111'], $o6['curl'][CURLOPT_RESOLVE]);
    }

    public function test_the_checked_url_is_rebuilt_without_a_trailing_dot(): void
    {
        $v = app(SafeFetcher::class)->vet('https://good.example.co.za./about?x=1#frag');
        $this->assertSame('good.example.co.za', $v['host']);
        $this->assertSame('https://good.example.co.za/about?x=1', $v['url']);
    }

    public static function moreNonPublicV6(): array
    {
        return [['::7f00:1'], ['fec0::1'], ['5f00::1'], ['3fff::1'], ['::ffff:0:7f00:1'], ['2001:db8::1']];
    }

    #[DataProvider('moreNonPublicV6')]
    public function test_other_non_public_ipv6_forms_are_refused(string $ip): void
    {
        $this->assertFalse(SafeFetcher::isPublicIp($ip), $ip);
    }

    public function test_a_redirect_from_https_down_to_http_is_refused(): void
    {
        Http::fake(['https://good.example.co.za/*' => Http::response('', 301, ['Location' => 'http://good.example.co.za/'])]);
        $this->expectException(FetchFailed::class);
        app(SafeFetcher::class)->get('https://good.example.co.za/');
    }

    public function test_the_shared_time_budget_stops_further_fetches_once_spent(): void
    {
        Http::fake(['*' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html'])]);
        $f = app(SafeFetcher::class);
        $f->startClock(-5);
        $this->expectException(FetchFailed::class);
        $f->get('https://good.example.co.za/');
    }
}
