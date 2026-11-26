<?php

namespace Tests\Feature\CentralLogin;

use App\Models\PlatformBranding;
use App\Models\PlatformCatalogueEvent;
use App\Support\Branding\PlatformBrandingView;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** R-54: the admin side of branding: gate, validation, versioning, uploads, audit. */
class BrandingAdminTest extends CentralLoginTestCase
{
    private const WHY = 'Approved with the owner on the Monday call.';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function fields(array $over = []): array
    {
        return array_merge(['mode' => 'dark', 'primary_color' => '#f2a70b', 'headline_login' => 'Back on shift', 'active' => '1', 'reason' => self::WHY], $over);
    }

    private function png(int $w = 200, int $h = 120): UploadedFile
    {
        $im = imagecreatetruecolor($w, $h);
        imagefill($im, 0, 0, imagecolorallocate($im, 30, 90, 160));
        ob_start();
        imagepng($im);
        $bytes = (string) ob_get_clean();

        return UploadedFile::fake()->createWithContent('logo.png', $bytes);
    }

    public function test_only_platform_administrators_open_the_pages(): void
    {
        $url = '/admin/platforms/branding/central';

        $this->get($url)->assertRedirect('/login');
        $this->actingAs($this->newcomer())->get($url)->assertForbidden();
        $this->actingAs($this->newcomer())->post($url, $this->fields())->assertForbidden();

        $moderator = $this->newcomer();
        $moderator->forceFill(['is_moderator' => true, 'two_factor_secret' => encrypt('test-secret'), 'two_factor_confirmed_at' => now()])->save();
        $this->actingAs($moderator->fresh())->get('/admin/platforms/branding')->assertForbidden();

        $this->actingAs($this->administrator())->get('/admin/platforms/branding')->assertOk()->assertSee('Dot.Central');
        $this->get($url)->assertOk()->assertSee('Sign-in headline');
    }

    public function test_publishing_creates_version_one_then_moves_it_by_one_and_audits_both(): void
    {
        $admin = $this->administrator();

        $this->actingAs($admin)->post('/admin/platforms/branding/central', $this->fields())->assertRedirect()->assertSessionHas('status');
        $row = PlatformBranding::firstOrFail();
        $this->assertSame(1, $row->version);
        $this->assertSame($admin->id, $row->updated_by);
        $this->assertSame('#f2a70b', $row->primary_color);

        $this->post('/admin/platforms/branding/central', $this->fields(['headline_login' => 'Welcome back, shift lead']));
        $this->assertSame(2, $row->fresh()->version);

        // Nothing different: no write, no new version, no new audit entry.
        $this->post('/admin/platforms/branding/central', $this->fields(['headline_login' => 'Welcome back, shift lead']));
        $this->assertSame(2, $row->fresh()->version);

        $events = PlatformCatalogueEvent::where('subject_type', 'branding')->orderBy('id')->get();
        $this->assertCount(2, $events);
        $this->assertSame(['branding_created', 'branding_published'], $events->pluck('type')->all());
        $this->assertSame('central', $events[1]->subject_key);
        $this->assertSame('Back on shift', $events[1]->before['headline_login']);
        $this->assertSame(self::WHY, $events[1]->reason);
        $this->assertSame($admin->id, $events[1]->actor_id);
    }

    public function test_a_reason_is_required_and_colours_must_be_hex(): void
    {
        $this->actingAs($this->administrator());

        $this->post('/admin/platforms/branding/central', $this->fields(['reason' => 'short']))->assertSessionHasErrors('reason');
        $this->post('/admin/platforms/branding/central', $this->fields(['primary_color' => 'red']))->assertSessionHasErrors('primary_color');
        $this->post('/admin/platforms/branding/central', $this->fields(['accent_color' => '#12345']))->assertSessionHasErrors('accent_color');
        $this->post('/admin/platforms/branding/central', $this->fields(['mode' => 'neon']))->assertSessionHasErrors('mode');
        $this->post('/admin/platforms/branding/central', $this->fields(['headline_login' => str_repeat('x', 121)]))->assertSessionHasErrors('headline_login');
        $this->assertSame(0, PlatformBranding::count());
    }

    public function test_approved_domains_must_belong_to_the_platform(): void
    {
        $this->actingAs($this->administrator());

        $this->post('/admin/platforms/branding/central', $this->fields(['approved_redirect_domains' => "evil.example\ncentral.test"]))->assertSessionHasErrors('approved_redirect_domains');
        $this->post('/admin/platforms/branding/central', $this->fields(['approved_redirect_domains' => 'central.test.evil.example']))->assertSessionHasErrors('approved_redirect_domains');
        $this->post('/admin/platforms/branding/central', $this->fields(['approved_redirect_domains' => "central.test\napp.central.test"]))->assertSessionHasNoErrors();

        $this->assertSame(['central.test', 'app.central.test'], PlatformBranding::firstOrFail()->approved_redirect_domains);
    }

    public function test_html_in_words_is_stripped_on_the_way_in(): void
    {
        $this->actingAs($this->administrator())->post('/admin/platforms/branding/central', $this->fields(['headline_login' => '<img src=x onerror=alert(1)>Hello']));

        $this->assertSame('Hello', PlatformBranding::firstOrFail()->headline_login);
    }

    public function test_preview_shows_the_result_and_saves_nothing(): void
    {
        $this->actingAs($this->administrator())->post('/admin/platforms/branding/central/preview', $this->fields(['headline_login' => 'Previewed headline']))
            ->assertOk()->assertSee('Previewed headline')->assertSee('Preview (not saved)');

        $this->assertSame(0, PlatformBranding::count());
        $this->assertSame(0, PlatformCatalogueEvent::where('subject_type', 'branding')->count());
        Storage::disk('local')->assertDirectoryEmpty('/');
    }

    public function test_a_logo_is_validated_re_encoded_stored_and_served_from_our_own_route(): void
    {
        $this->actingAs($this->administrator())->post('/admin/platforms/branding/central', $this->fields(['logo' => $this->png()]))->assertSessionHasNoErrors();

        $path = PlatformBranding::firstOrFail()->logo_path;
        $this->assertMatchesRegularExpression('#\Aplatform-branding/central/logo-[0-9a-f]{16}\.png\z#', $path);
        Storage::disk('local')->assertExists($path);

        $this->flushSession();
        $response = $this->get('/brand/platform/central/logo?v=1')->assertOk();
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertSame('nosniff', $response->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));

        $view = PlatformBrandingView::forPlatform($this->platform->fresh());
        $this->assertStringContainsString('/brand/platform/central/logo?v=1', (string) $view->logoUrl);
    }

    public function test_uploads_that_are_not_clean_images_are_refused(): void
    {
        $this->actingAs($this->administrator());

        $svg = UploadedFile::fake()->createWithContent('logo.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>');
        $this->post('/admin/platforms/branding/central', $this->fields(['logo' => $svg]))->assertSessionHasErrors('logo');

        $svgNamed = UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"></svg>');
        $this->post('/admin/platforms/branding/central', $this->fields(['logo' => $svgNamed]))->assertSessionHasErrors('logo');

        $php = UploadedFile::fake()->createWithContent('logo.png', "<?php system('id'); ?>");
        $this->post('/admin/platforms/branding/central', $this->fields(['logo' => $php]))->assertSessionHasErrors('logo');

        $tiny = $this->png(32, 32);
        $this->post('/admin/platforms/branding/central', $this->fields(['logo' => $tiny]))->assertSessionHasErrors('logo');

        $this->post('/admin/platforms/branding/central', $this->fields(['hero' => $this->png(400, 300)]))->assertSessionHasErrors('hero'); // below the hero minimum

        $big = UploadedFile::fake()->create('hero.png', 3000, 'image/png');
        $this->post('/admin/platforms/branding/central', $this->fields(['hero' => $big]))->assertSessionHasErrors('hero');

        $this->assertSame(0, PlatformBranding::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_a_payload_appended_to_an_image_does_not_survive(): void
    {
        $im = imagecreatetruecolor(300, 200);
        ob_start();
        imagepng($im);
        $bytes = ob_get_clean().'<?php echo "pwned"; ?><script>alert(1)</script>';
        $file = UploadedFile::fake()->createWithContent('logo.png', $bytes);

        $this->actingAs($this->administrator())->post('/admin/platforms/branding/central', $this->fields(['logo' => $file]))->assertSessionHasNoErrors();

        $stored = Storage::disk('local')->get(PlatformBranding::firstOrFail()->logo_path);
        $this->assertStringNotContainsString('pwned', $stored);
        $this->assertStringNotContainsString('<script', $stored);
    }

    public function test_images_can_be_removed_and_the_image_route_only_serves_what_the_row_names(): void
    {
        $this->actingAs($this->administrator())->post('/admin/platforms/branding/central', $this->fields(['logo' => $this->png()]));
        $this->post('/admin/platforms/branding/central', $this->fields(['remove_logo' => '1']));
        $this->assertNull(PlatformBranding::firstOrFail()->logo_path);

        $this->get('/brand/platform/central/logo')->assertNotFound();
        $this->get('/brand/platform/central/hero')->assertNotFound();
        $this->get('/brand/platform/nope/logo')->assertNotFound();
        $this->get('/brand/platform/central/secret')->assertNotFound();
        $this->get('/brand/platform/..%2F..%2Fetc/logo')->assertNotFound();
    }

    public function test_a_tampered_stored_path_is_never_served(): void
    {
        Storage::disk('local')->put('secret.txt', 'top secret');
        PlatformBranding::create(['dot_platform_id' => $this->platform->id, 'logo_path' => '../../secret.txt']);
        PlatformBranding::query()->update(['hero_path' => 'secret.txt']);

        $this->get('/brand/platform/central/logo')->assertNotFound();
        $this->get('/brand/platform/central/hero')->assertNotFound();
    }

    public function test_the_audit_page_lists_branding_changes(): void
    {
        $this->actingAs($this->administrator())->post('/admin/platforms/branding/central', $this->fields());

        $this->get('/admin/platforms/audit?about=branding')->assertOk()->assertSee('Sign-in branding created');
    }
}
