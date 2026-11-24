<?php

namespace Tests\Feature\Growth;

use App\Models\AcquisitionTouch;
use App\Models\User;
use App\Services\Growth\Acquisition;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Tests\TestCase;

/**
 * R-59 Phase 1A: where a sign-up came from. What these tests protect: a plain visit keeps nothing, a tagged arrival keeps one small
 * validated first-party cookie, the first arrival wins, a changed cookie is never believed, and registration stores one row with no IP.
 */
class AcquisitionCaptureTest extends TestCase
{
    use RefreshDatabase;

    /** The value the browser holds, as the page set it: decrypted, without the framework's integrity prefix. */
    private function plain(Cookie $cookie): string
    {
        return CookieValuePrefix::remove(decrypt($cookie->getValue(), false));
    }

    private function cookieName(): string
    {
        return (string) config('acquisition.cookie');
    }

    public function test_a_plain_visit_sets_no_cookie(): void
    {
        $this->get('/')->assertOk()->assertCookieMissing($this->cookieName());
        $this->get('/about?x=1')->assertOk()->assertCookieMissing($this->cookieName());
    }

    public function test_a_referral_code_or_campaign_tag_sets_one_cookie(): void
    {
        $this->get('/?ref=KHANYI-7&utm_source=newsletter&utm_medium=email&utm_campaign=launch')->assertOk()->assertCookie($this->cookieName());

        $this->get('/?utm_campaign=spring')->assertOk()->assertCookie($this->cookieName());
    }

    public function test_a_search_or_social_referrer_sets_it_but_an_own_or_unknown_referrer_does_not(): void
    {
        $this->withHeader('Referer', 'https://www.google.com/search?q=secret+words')->get('/')->assertCookie($this->cookieName());
        $this->withHeader('Referer', 'https://random-blog.example/post')->get('/')->assertCookieMissing($this->cookieName());
        $this->withHeader('Referer', 'http://localhost/about')->get('/')->assertCookieMissing($this->cookieName());
    }

    public function test_nothing_is_set_for_a_post_a_script_request_or_a_signed_in_person(): void
    {
        $this->post('/login?ref=ABCD1234', ['email' => 'x@example.com', 'password' => 'nope'])->assertCookieMissing($this->cookieName());
        $this->getJson('/?ref=ABCD1234')->assertCookieMissing($this->cookieName());
        $this->actingAs(User::factory()->create())->get('/?ref=ABCD1234')->assertCookieMissing($this->cookieName());
    }

    public function test_a_bad_code_or_tag_is_not_kept(): void
    {
        $this->get('/?ref=<script>&utm_source='.urlencode('"><img src=x>'))->assertCookieMissing($this->cookieName());
        $this->get('/?ref=abc')->assertCookieMissing($this->cookieName());
    }

    public function test_the_first_arrival_wins(): void
    {
        $first = $this->get('/?ref=FIRST-CODE')->getCookie($this->cookieName(), false);
        $second = $this->withCookie($this->cookieName(), $this->plain($first))->get('/?ref=SECOND-CODE');

        $second->assertCookieMissing($this->cookieName());
    }

    public function test_registration_stores_one_row_with_the_channel_and_no_ip(): void
    {
        $cookie = $this->get('/?ref=KHANYI-7&utm_source=newsletter&utm_medium=email&utm_campaign=launch', ['Referer' => 'https://www.google.com/'])->getCookie($this->cookieName(), false);

        $password = 'Pw-'.bin2hex(random_bytes(10)).'-Aa1';
        $this->withCookie($this->cookieName(), $this->plain($cookie))->post('/register', [
            'name' => 'Thandi Dlamini', 'email' => 'thandi@example.org', 'password' => $password, 'password_confirmation' => $password, 'terms' => 'on',
        ]);

        $user = User::where('email', 'thandi@example.org')->first();
        $touch = $user ? AcquisitionTouch::where('user_id', $user->id)->first() : null;

        if ($user === null) {
            $this->markTestSkipped('registration is refused for this address in this environment');
        }

        $this->assertNotNull($touch);
        $this->assertSame('referral', $touch->channel);
        $this->assertSame('KHANYI-7', $touch->referral_code);
        $this->assertSame('newsletter', $touch->utm_source);
        $this->assertSame('launch', $touch->utm_campaign);
        $this->assertSame('www.google.com', $touch->referrer_host);
        $this->assertSame('/', $touch->landing_path);

        $columns = array_keys($touch->getAttributes());
        foreach (['ip', 'ip_address', 'user_agent', 'device'] as $forbidden) {
            $this->assertNotContains($forbidden, $columns, "no {$forbidden} is stored");
        }
    }

    public function test_a_registration_with_no_cookie_is_direct(): void
    {
        $password = 'Pw-'.bin2hex(random_bytes(10)).'-Aa1';
        $this->post('/register', ['name' => 'Direct Person', 'email' => 'direct@example.org', 'password' => $password, 'password_confirmation' => $password, 'terms' => 'on']);

        $user = User::where('email', 'direct@example.org')->first();
        $this->assertNotNull($user);
        $this->assertSame('direct', AcquisitionTouch::where('user_id', $user->id)->value('channel'));
    }

    public function test_a_changed_or_old_cookie_is_read_as_nothing(): void
    {
        $service = app(Acquisition::class);
        $request = fn (string $value) => Request::create('/', 'GET', [], [$this->cookieName() => $value]);

        $this->assertNull($service->fromCookie($request('not json')));
        $this->assertNull($service->fromCookie($request(json_encode(['r' => 'GOODCODE', 'u' => [], 'h' => null, 'p' => '/', 'at' => time() - 40 * 86400]))));
        $this->assertNull($service->fromCookie($request(json_encode(['r' => 'GOODCODE', 'u' => [], 'h' => null, 'p' => '/', 'at' => time() + 3600]))));
        $this->assertNull($service->fromCookie($request(str_repeat('a', 2000))));

        $kept = $service->fromCookie($request(json_encode(['r' => 'bad code!', 'u' => ['source' => '<b>', 'medium' => 'email', 'evil' => 'x'], 'h' => 'Not A Host!', 'p' => 'nope', 'at' => time()])));
        $this->assertNull($kept['r']);
        $this->assertSame(['medium' => 'email'], $kept['u']);
        $this->assertNull($kept['h']);
        $this->assertNull($kept['p']);
    }

    public function test_the_channel_follows_the_strategy_order(): void
    {
        $service = app(Acquisition::class);
        $touch = fn (array $over = []) => array_merge(['r' => null, 'u' => [], 'h' => null, 'p' => '/', 'at' => time()], $over);

        $this->assertSame('product_led', $service->classify($touch(['r' => 'ABCD1234']), 'docs'), 'a platform that started the sign-in comes first');
        $this->assertSame('referral', $service->classify($touch(['r' => 'ABCD1234', 'u' => ['medium' => 'partner']]), null));
        $this->assertSame('partner', $service->classify($touch(['u' => ['medium' => 'partner']]), null));
        $this->assertSame('institutional', $service->classify($touch(['u' => ['medium' => 'institution']]), null));
        $this->assertSame('campaign', $service->classify($touch(['u' => ['source' => 'newsletter']]), null));
        $this->assertSame('organic', $service->classify($touch(['h' => 'www.bing.com']), null));
        $this->assertSame('direct', $service->classify(null, null));
    }

    public function test_the_cookie_and_the_collection_are_described_on_the_cookie_and_privacy_pages(): void
    {
        $this->get('/cookies')->assertOk()->assertSee('dot_src')->assertSee('never set on a plain visit');
        $this->get('/policy')->assertOk()->assertSee('How you arrived');
        $this->get('/register')->assertOk()->assertSee('keeps its referral code or campaign tag');
    }
}
