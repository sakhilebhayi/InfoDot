<?php

namespace Tests\Unit;

use App\Models\DotPlatform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** A registered origin root matches with or without its trailing slash; nothing else loosens. */
class PostLogoutOriginSlashTest extends TestCase
{
    private function platform(string ...$uris): DotPlatform
    {
        $p = new DotPlatform;
        $p->post_logout_redirect_uris = $uris;

        return $p;
    }

    #[DataProvider('accepted')]
    public function test_origin_root_matches_either_form(string $registered, string $sent): void
    {
        $this->assertTrue($this->platform($registered)->allowsPostLogoutRedirect($sent));
    }

    public static function accepted(): array
    {
        return [
            'registered with slash, sent without' => ['https://charts.infodot.co.za/', 'https://charts.infodot.co.za'],
            'registered without, sent with slash' => ['https://charts.infodot.co.za', 'https://charts.infodot.co.za/'],
            'identical with slash' => ['https://charts.infodot.co.za/', 'https://charts.infodot.co.za/'],
            'exact path unchanged' => ['https://central.test/sso/logged-out', 'https://central.test/sso/logged-out'],
        ];
    }

    #[DataProvider('refused')]
    public function test_nothing_else_loosens(string $registered, string $sent): void
    {
        $this->assertFalse($this->platform($registered)->allowsPostLogoutRedirect($sent));
    }

    public static function refused(): array
    {
        return [
            'different host' => ['https://charts.infodot.co.za/', 'https://evil.example/'],
            'lookalike host suffix' => ['https://charts.infodot.co.za/', 'https://charts.infodot.co.za.evil.example/'],
            'userinfo trick' => ['https://charts.infodot.co.za/', 'https://charts.infodot.co.za@evil.example/'],
            'extra path' => ['https://charts.infodot.co.za/', 'https://charts.infodot.co.za/phish'],
            'trailing slash on a path' => ['https://central.test/sso/logged-out', 'https://central.test/sso/logged-out/'],
            'query added' => ['https://charts.infodot.co.za/', 'https://charts.infodot.co.za/?x=1'],
            'scheme downgrade' => ['https://charts.infodot.co.za/', 'http://charts.infodot.co.za/'],
            'double slash' => ['https://charts.infodot.co.za/', 'https://charts.infodot.co.za//'],
        ];
    }
}
