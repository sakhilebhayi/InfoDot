<?php

namespace Tests\Feature;

use App\Livewire\DotSwitcher;
use App\Livewire\EcosystemWidget;
use App\Models\Team;
use App\Models\User;
use App\Support\EcosystemHandoff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The handoff token is a credential. It must only ever be sent to a live
 * platform on a domain we control. The registry used to default to
 * *.infodot.app, which nobody had registered.
 */
class EcosystemHandoffTest extends TestCase
{
    use RefreshDatabase;

    private function platform(string $url, bool $active = true): array
    {
        return ['name' => 'X', 'url' => $url, 'active' => $active];
    }

    private function user(): User
    {
        $user = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => true]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user;
    }

    public function test_it_accepts_live_hosts_and_builds_the_endpoint_from_scheme_host_and_port_only(): void
    {
        foreach (['https://forms.infodot.co.za', 'https://pres.infodot.co.za', 'https://sheets.infodot.co.za', 'https://doc.infodot.co.za', 'https://infodot.co.za'] as $url) {
            $this->assertSame($url.'/auth/ecosystem', EcosystemHandoff::endpointFor($this->platform($url)), $url);
        }

        $this->assertSame(
            'https://forms.infodot.co.za/auth/ecosystem',
            EcosystemHandoff::endpointFor($this->platform('https://FORMS.infodot.co.za/some/path?x=1#frag')),
            'a path, query or fragment in the registry must not survive into the redirect',
        );
    }

    public function test_it_refuses_hosts_that_are_not_ours(): void
    {
        foreach ([
            'https://forms.infodot.app',            // the old, unregistered default
            'https://infodot.app',
            'https://evil.example',
            'https://infodot.co.za.evil.example',   // look-alike suffix
            'https://evil.example/.infodot.co.za',  // domain only in the path
            'https://evil.example?.infodot.co.za',  // domain only in the query
            'https://notinfodot.co.za',             // not a subdomain boundary
            'https://x@evil.example',               // userinfo
            'https://user:pw@forms.infodot.co.za',  // userinfo even on a good host
            'http://forms.infodot.co.za',           // not https
            'javascript:alert(1)',
            '//forms.infodot.co.za',
            '',
        ] as $url) {
            $this->assertNull(EcosystemHandoff::endpointFor($this->platform($url)), "should refuse [{$url}]");
        }
    }

    public function test_it_refuses_an_inactive_platform_and_a_missing_one(): void
    {
        $this->assertNull(EcosystemHandoff::endpointFor($this->platform('https://tasks.infodot.co.za', active: false)));
        $this->assertNull(EcosystemHandoff::endpointFor(null));
        $this->assertNull(EcosystemHandoff::endpointFor(['name' => 'No url']));
    }

    public function test_insecure_http_is_allowed_only_when_explicitly_switched_on_for_a_trusted_host(): void
    {
        config(['ecosystem.allow_insecure_handoff' => true, 'ecosystem.trusted_domains' => ['localhost']]);

        $this->assertSame('http://app.localhost:8000/auth/ecosystem', EcosystemHandoff::endpointFor($this->platform('http://app.localhost:8000')));
        $this->assertNull(EcosystemHandoff::endpointFor($this->platform('http://evil.example')));
    }

    public function test_the_shipped_registry_defaults_only_hand_off_to_trusted_hosts(): void
    {
        // Parsed from the file, not from config(): a local or production .env may
        // legitimately override DOT_*_URL, and this test is about the defaults.
        preg_match_all("/'(?<key>[a-z]+)' => \\['name' => '[^']+', 'url' => env\\('DOT_[A-Z]+_URL', '(?<url>[^']+)'\\), 'icon' => '[a-z_]+', 'accent' => '#[0-9a-f]{6}', 'active' => (?<active>true|false)(?:, 'internal' => true)?\\]/", file_get_contents(base_path('config/ecosystem.php')), $m, PREG_SET_ORDER);

        $this->assertCount(28, $m);

        foreach ($m as $row) {
            $endpoint = EcosystemHandoff::endpointFor(['url' => $row['url'], 'active' => $row['active'] === 'true']);

            if ($row['active'] === 'true') {
                $this->assertNotNull($endpoint, "{$row['key']} is active but would be refused");
            } else {
                $this->assertNull($endpoint, "{$row['key']} is inactive and must not receive a token");
            }
        }
    }

    public function test_switcher_sends_the_browser_to_the_launch_page_for_a_trusted_live_platform_and_no_token_is_in_the_url(): void
    {
        config(['ecosystem.platforms.files.url' => 'https://files.infodot.co.za', 'ecosystem.platforms.files.active' => true]);

        $component = Livewire::actingAs($this->user())
            ->test(DotSwitcher::class)
            ->call('switchTo', 'files')
            ->assertRedirect(route('platform.launch', ['platform' => 'files']));

        $this->assertStringNotContainsString('token', (string) data_get($component->effects, 'redirect'));
        $this->assertSame(0, DB::table('personal_access_tokens')->count(), 'the token is minted on the launch page, not on the click');
    }

    public function test_switcher_creates_no_token_and_does_not_redirect_for_a_hijackable_host(): void
    {
        config(['ecosystem.platforms.files.url' => 'https://files.infodot.app']);

        Livewire::actingAs($this->user())
            ->test(DotSwitcher::class)
            ->call('switchTo', 'files')
            ->assertNoRedirect();

        $this->assertSame(0, DB::table('personal_access_tokens')->count(), 'a refused destination must never get a token');
    }

    public function test_switcher_refuses_an_inactive_platform(): void
    {
        config(['ecosystem.platforms.tasks.url' => 'https://tasks.infodot.co.za', 'ecosystem.platforms.tasks.active' => false]);

        Livewire::actingAs($this->user())
            ->test(DotSwitcher::class)
            ->call('switchTo', 'tasks')
            ->assertNoRedirect();

        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_widget_sends_the_browser_to_the_launch_page_for_a_trusted_live_platform(): void
    {
        config(['ecosystem.platforms.forms.url' => 'https://forms.infodot.co.za', 'ecosystem.platforms.forms.active' => true]);

        Livewire::actingAs($this->user())
            ->test(EcosystemWidget::class)
            ->call('launch', 'forms')
            ->assertRedirect(route('platform.launch', ['platform' => 'forms']));

        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_widget_creates_no_token_for_a_hijackable_host(): void
    {
        config(['ecosystem.platforms.forms.url' => 'https://evil.example']);

        Livewire::actingAs($this->user())
            ->test(EcosystemWidget::class)
            ->call('launch', 'forms')
            ->assertNoRedirect();

        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }
}
