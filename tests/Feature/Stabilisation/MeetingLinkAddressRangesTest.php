<?php

namespace Tests\Feature\Stabilisation;

use App\Services\Seminars\MeetingLinkCheck;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * R-49. Before a meeting link is opened to check it, the name is resolved and refused when it points inside a
 * network. The test used PHP's "private" and "reserved" ranges, which leave out several that are not public
 * either: shared carrier space (used inside hosting networks), benchmarking, protocol assignments, multicast and
 * the IPv6 forms that carry an IPv4 address. Those are now refused too, and nothing is requested from them.
 */
class MeetingLinkAddressRangesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['seminars.link_check.enabled' => true]);
        Http::fake(['*' => Http::response('', 200)]);
    }

    protected function tearDown(): void
    {
        MeetingLinkCheck::$resolver = null;
        parent::tearDown();
    }

    /** @return array<string, array{0: string}> */
    public static function notPublic(): array
    {
        return [
            'shared carrier space' => ['100.64.0.10'],
            'benchmarking' => ['198.18.0.1'],
            'protocol assignments' => ['192.0.0.8'],
            'multicast' => ['224.0.0.1'],
            'ipv6 multicast' => ['ff02::1'],
            'ipv4 inside ipv6 (6to4)' => ['2002:a00:1::1'],
            'ipv4 inside ipv6 (translation prefix)' => ['64:ff9b::a00:1'],
            'private, as before' => ['10.0.0.1'],
            'link local, as before' => ['169.254.169.254'],
        ];
    }

    #[DataProvider('notPublic')]
    public function test_a_name_that_resolves_inside_a_network_is_refused_and_never_requested(string $ip): void
    {
        MeetingLinkCheck::$resolver = fn (string $host) => [$ip];

        $result = app(MeetingLinkCheck::class)->check('https://meet.example.com/room');

        $this->assertSame('bad', $result['status']);
        $this->assertStringContainsString('private address', $result['message']);
        Http::assertNothingSent();
    }

    public function test_a_public_address_is_still_checked(): void
    {
        MeetingLinkCheck::$resolver = fn (string $host) => ['93.184.216.34'];

        $this->assertSame('ok', app(MeetingLinkCheck::class)->check('https://meet.example.com/room')['status']);
        Http::assertSentCount(1);
    }
}
