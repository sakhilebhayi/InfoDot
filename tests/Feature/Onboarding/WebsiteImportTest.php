<?php

namespace Tests\Feature\Onboarding;

use App\Models\CompanyProfileImport;
use App\Models\Team;
use App\Models\User;
use App\Services\Onboarding\WebsiteExtractor;
use App\Services\Web\FetchFailed;
use App\Services\Web\SafeFetcher;
use App\Services\Web\UnsafeUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/** R-62 phase 3: reading a company's own website without guessing, then letting the person check it. */
class WebsiteImportTest extends TestCase
{
    use RefreshDatabase;

    private const HOME = <<<'HTML'
<!doctype html><html><head><title>Dlamini Freight | Moving Durban since 2009</title>
<meta name="description" content="Short page description.">
<meta property="og:site_name" content="Dlamini Freight (Pty) Ltd">
<script type="application/ld+json">
{"@context":"https://schema.org","@graph":[
 {"@type":"WebSite","name":"Not the company","url":"https://dlaminifreight.co.za"},
 {"@type":"LocalBusiness","name":"Dlamini Freight","description":"We move freight across KwaZulu-Natal.","telephone":"+27 31 555 0100",
  "email":"mailto:HELLO@dlaminifreight.co.za","address":{"@type":"PostalAddress","streetAddress":"12 Dock Road","addressLocality":"Durban","addressRegion":"KZN","postalCode":"4001","addressCountry":"ZA"},
  "sameAs":["https://www.linkedin.com/company/dlamini-freight","https://www.facebook.com/dlaminifreight","https://example.org/not-social"]}
]}
</script></head><body>
<a href="/about-us">About</a> <a href="/contact">Contact us</a> <a href="/services/freight">Services</a> <a href="https://elsewhere.example.co.za/about">External about</a>
<a href="mailto:info@dlaminifreight.co.za">Email</a> <a href="https://twitter.com/intent/tweet?text=hi">Share</a>
</body></html>
HTML;

    protected function setUp(): void
    {
        parent::setUp();
        SafeFetcher::$resolver = fn (string $host) => ['203.0.114.10'];
        Cache::flush();
        RateLimiter::clear('company-import:1');
    }

    protected function tearDown(): void
    {
        SafeFetcher::$resolver = null;
        parent::tearDown();
    }

    private function site(array $extra = []): void
    {
        Http::fake($extra + [
            'dlaminifreight.co.za/robots.txt' => Http::response("User-agent: *\nDisallow: /admin\n", 200, ['Content-Type' => 'text/plain']),
            'dlaminifreight.co.za/about-us' => Http::response('<html><body><a href="https://www.instagram.com/dlaminifreight/">IG</a><a href="tel:0315550100">Call</a></body></html>', 200, ['Content-Type' => 'text/html']),
            'dlaminifreight.co.za/contact' => Http::response('<html><body><a href="mailto:accounts@dlaminifreight.co.za">Accounts</a></body></html>', 200, ['Content-Type' => 'text/html']),
            'dlaminifreight.co.za/services/freight' => Http::response('<html><body>Freight</body></html>', 200, ['Content-Type' => 'text/html']),
            'dlaminifreight.co.za/*' => Http::response(self::HOME, 200, ['Content-Type' => 'text/html; charset=utf-8']),
        ]);
    }

    private function person(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => true, 'name' => 'Personal']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user->fresh();
    }

    public function test_the_extractor_reads_what_the_site_publishes_and_nothing_else(): void
    {
        $this->site();

        $result = app(WebsiteExtractor::class)->extract('dlaminifreight.co.za');
        $f = $result['fields'];

        $this->assertSame('Dlamini Freight', $f['name']['value'], 'structured data beats the Open Graph name and the page title');
        $this->assertSame(0.9, $f['name']['confidence']);
        $this->assertSame('We move freight across KwaZulu-Natal.', $f['description']['value']);
        $this->assertSame('+27 31 555 0100', $f['contact_phone']['value']);
        $this->assertSame('hello@dlaminifreight.co.za', $f['contact_email']['value']);
        $this->assertSame('12 Dock Road, Durban, KZN, 4001, ZA', $f['location']['value']);
        $this->assertSame('https://dlaminifreight.co.za', $f['website']['value']);
        $this->assertSame(['linkedin', 'facebook', 'instagram'], array_keys($f['links']['value']), 'a share button and a non-social site are not profiles');
        $this->assertArrayNotHasKey('industry', $f, 'an industry is never guessed');
        $this->assertArrayNotHasKey('registration_number', $f);
    }

    public function test_only_same_site_pages_are_read_and_robots_txt_is_honoured_per_page(): void
    {
        $this->site(['dlaminifreight.co.za/robots.txt' => Http::response("User-agent: *\nDisallow: /contact\n", 200, ['Content-Type' => 'text/plain'])]);

        $result = app(WebsiteExtractor::class)->extract('https://dlaminifreight.co.za');

        $this->assertNotContains('https://dlaminifreight.co.za/contact', $result['pages']);
        $this->assertContains('https://dlaminifreight.co.za/about-us', $result['pages']);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'elsewhere.example.co.za'));
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/contact') && ! str_contains($r->url(), 'robots'));
        $this->assertLessThanOrEqual(5, count($result['pages']));
    }

    public function test_a_site_that_forbids_reading_or_cannot_be_reached_is_not_read(): void
    {
        $this->site(['dlaminifreight.co.za/robots.txt' => Http::response("User-agent: *\nDisallow: /\n", 200, ['Content-Type' => 'text/plain'])]);

        try {
            app(WebsiteExtractor::class)->extract('dlaminifreight.co.za');
            $this->fail('A site that forbids reading was read.');
        } catch (FetchFailed $e) {
            $this->assertStringContainsString('asks automated readers not to read it', $e->getMessage());
        }
        Http::assertNotSent(fn ($r) => $r->url() === 'https://dlaminifreight.co.za');

        $this->expectException(UnsafeUrl::class);
        app(WebsiteExtractor::class)->extract('http://169.254.169.254/');
    }

    public function test_markup_in_a_page_is_reduced_to_text_and_a_blank_page_gives_only_the_address(): void
    {
        $this->site([
            'dlaminifreight.co.za/*' => Http::response('<html><head><meta property="og:site_name" content="Acme &amp; Sons <img src=x onerror=alert(1)>"></head><body></body></html>', 200, ['Content-Type' => 'text/html']),
        ]);
        $name = app(WebsiteExtractor::class)->extract('dlaminifreight.co.za')['fields']['name']['value'];
        $this->assertSame('Acme & Sons', $name);

        $this->site(['dlaminifreight.co.za/*' => Http::response('<html><body>Hello</body></html>', 200, ['Content-Type' => 'text/html'])]);
        $this->assertSame(['website'], array_keys(app(WebsiteExtractor::class)->extract('dlaminifreight.co.za')['fields']));
    }

    public function test_reading_a_website_fills_the_form_for_review_and_creates_nothing(): void
    {
        $this->site();
        $user = $this->person();

        $this->actingAs($user)->post(route('onboarding.business.website'), ['website' => 'dlaminifreight.co.za'])
            ->assertRedirect(route('onboarding.business', ['import' => 1]));

        $import = CompanyProfileImport::sole();
        $this->assertSame(['website', 'draft', 'dlaminifreight.co.za'], [$import->source, $import->status, $import->source_ref]);
        $this->assertSame(0, Team::where('personal_team', false)->count(), 'nothing exists until the person confirms');

        $this->actingAs($user)->get(route('onboarding.business', ['import' => $import->id]))->assertOk()
            ->assertSee('We read dlaminifreight.co.za')->assertSee('value="Dlamini Freight"', false)->assertSee('We move freight across KwaZulu-Natal.')
            ->assertSee('Read from dlaminifreight.co.za')->assertSee('We never guess an industry')->assertSee('hello@dlaminifreight.co.za', false)
            ->assertSee('https://www.linkedin.com/company/dlamini-freight', false)->assertDontSee('Have a website? Let us read it.');
    }

    public function test_confirming_records_where_each_value_came_from_and_which_the_person_changed(): void
    {
        $this->site();
        $user = $this->person();
        $this->actingAs($user)->post(route('onboarding.business.website'), ['website' => 'dlaminifreight.co.za'])->assertRedirect();
        $import = CompanyProfileImport::sole();

        $this->actingAs($user)->post(route('onboarding.business.store'), [
            'import' => $import->id, 'name' => 'Dlamini Freight', 'industry' => 'logistics', 'description' => 'We move freight, our own words.', 'visibility' => 'public',
            'website' => 'https://dlaminifreight.co.za', 'contact_email' => 'hello@dlaminifreight.co.za', 'contact_phone' => '+27 31 555 0100', 'location' => 'Durban',
            'links' => ['linkedin' => 'https://www.linkedin.com/company/dlamini-freight', 'facebook' => ''],
        ])->assertRedirect(route('onboarding.recommendations'));

        $team = Team::where('name', 'Dlamini Freight')->firstOrFail();
        $profile = $team->profile()->first();
        $sources = $profile->field_sources;

        $this->assertSame('website', $sources['name']['source']);
        $this->assertSame('website', $sources['contact_email']['source']);
        $this->assertSame('manual', $sources['description']['source'], 'the person rewrote it');
        $this->assertSame('manual', $sources['industry']['source'], 'the person chose it; a website never supplies one');
        $this->assertSame('manual', $sources['location']['source'], 'shortened by the person');
        $this->assertSame(['linkedin' => 'https://www.linkedin.com/company/dlamini-freight'], $profile->links);
        $this->assertSame('We move freight, our own words.', $profile->description);

        $import->refresh();
        $this->assertSame(['website', 'confirmed', $team->id], [$import->source, $import->status, $import->team_id]);
    }

    public function test_an_unsafe_blocked_or_empty_site_says_so_and_offers_typing_instead(): void
    {
        $user = $this->person();
        $cases = [
            'http://169.254.169.254/latest/meta-data/' => 'private or reserved network',
            'localhost' => 'not a web address',
            'ftp://dlaminifreight.co.za' => 'Only http and https',
        ];

        foreach ($cases as $input => $expected) {
            $this->actingAs($user)->from(route('onboarding.business'))->post(route('onboarding.business.website'), ['website' => $input])
                ->assertRedirect(route('onboarding.business'))->assertSessionHasErrors('website');
            $this->assertStringContainsString($expected, session('errors')->first('website'));
            $this->assertStringContainsString('type the details in instead', session('errors')->first('website'));
        }

        $this->site(['dlaminifreight.co.za/*' => Http::response('<html><body>Nothing here</body></html>', 200, ['Content-Type' => 'text/html'])]);
        $this->actingAs($user)->post(route('onboarding.business.website'), ['website' => 'dlaminifreight.co.za'])->assertSessionHasErrors('website');
        $this->assertStringContainsString('could not find company details', session('errors')->first('website'));

        $this->assertSame(0, CompanyProfileImport::where('status', 'draft')->count(), 'a failed read leaves nothing open');
        $this->assertSame(0, Team::where('personal_team', false)->count());
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '169.254'));
    }

    public function test_reading_is_limited_per_person_per_day_and_needs_a_signed_in_person_without_a_company(): void
    {
        $this->site();
        $user = $this->person();
        RateLimiter::clear('company-import:'.$user->id);
        for ($i = 0; $i < 10; $i++) {
            RateLimiter::hit('company-import:'.$user->id, 86400);
        }

        $this->actingAs($user)->post(route('onboarding.business.website'), ['website' => 'dlaminifreight.co.za'])->assertSessionHasErrors('website');
        $this->assertStringContainsString('read a lot of websites today', session('errors')->first('website'));
        Http::assertNothingSent();

        $other = $this->person();
        $other->ownedTeams()->create(['name' => 'Has One', 'personal_team' => false]);
        $this->actingAs($other->fresh())->post(route('onboarding.business.website'), ['website' => 'dlaminifreight.co.za'])->assertRedirect(route('dashboard'));
    }

    public function test_a_guest_cannot_read_a_website(): void
    {
        Http::fake();

        $this->post(route('onboarding.business.website'), ['website' => 'dlaminifreight.co.za'])->assertRedirect(route('login'));
        Http::assertNothingSent();
    }

    public function test_a_robots_answer_that_is_not_a_robots_file_means_no_rules(): void
    {
        $this->site(['dlaminifreight.co.za/robots.txt' => Http::response('<html>app shell</html>', 200, ['Content-Type' => 'text/html'])]);

        $this->assertArrayHasKey('name', app(WebsiteExtractor::class)->extract('dlaminifreight.co.za')['fields']);
    }

    public function test_another_persons_import_is_never_used_to_fill_a_form(): void
    {
        $this->site();
        $owner = $this->person();
        $this->actingAs($owner)->post(route('onboarding.business.website'), ['website' => 'dlaminifreight.co.za'])->assertRedirect();
        $import = CompanyProfileImport::sole();

        $stranger = $this->person();
        $this->actingAs($stranger)->get(route('onboarding.business', ['import' => $import->id]))->assertOk()
            ->assertDontSee('We read dlaminifreight.co.za')->assertDontSee('value="Dlamini Freight"', false)->assertSee('Have a website? Let us read it.');
    }

    public function test_text_found_on_a_site_is_escaped_when_it_is_shown(): void
    {
        $this->site(['dlaminifreight.co.za/*' => Http::response('<html><head><meta property="og:site_name" content="&lt;script&gt;alert(1)&lt;/script&gt;Evil"><meta name="description" content="&quot;&gt;&lt;b onmouseover=x&gt;"></head></html>', 200, ['Content-Type' => 'text/html'])]);
        $user = $this->person();

        $this->actingAs($user)->post(route('onboarding.business.website'), ['website' => 'dlaminifreight.co.za'])->assertRedirect();
        $page = $this->actingAs($user)->get(route('onboarding.business', ['import' => CompanyProfileImport::sole()->id]))->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert(1)', $page);
        $this->assertStringNotContainsString('<b onmouseover', $page);
    }

    public function test_malformed_links_on_a_page_do_not_break_the_reader(): void
    {
        $html = '<html><head><title>Odd Site</title><meta property="og:site_name" content="Odd Site"></head><body>'
            .'<a href="http:///">a</a><a href="http://:80/x">b</a><a href="http://?a">c</a><a href="//">d</a><a href="/about">About</a></body></html>';
        Http::fake([
            '*/robots.txt' => Http::response('', 404),
            '*' => Http::response($html, 200, ['Content-Type' => 'text/html']),
        ]);

        $out = app(WebsiteExtractor::class)->extract('odd.example.co.za');

        $this->assertSame('Odd Site', $out['fields']['name']['value']);
    }
}
