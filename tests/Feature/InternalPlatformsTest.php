<?php

namespace Tests\Feature;

use App\Livewire\DotSwitcher;
use App\Livewire\EcosystemWidget;
use App\Models\Team;
use App\Models\User;
use App\Support\EcosystemHandoff;
use App\Support\Platforms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Memory, Brain and Dopemine connect the other platforms (shared memory, AI, connectivity).
 * People never see them, list them or open them; the platforms still talk to them.
 */
class InternalPlatformsTest extends TestCase
{
    use RefreshDatabase;

    private function member(): User
    {
        $user = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => false, 'name' => 'Northwind']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ecosystem.trusted_domains' => ['infodot.co.za'],
            'ecosystem.service_secrets' => ['memory' => 'memory-secret-for-tests', 'files' => 'files-secret'],
            'ecosystem.platforms' => [
                'infodot' => ['name' => 'InfoDot', 'url' => 'https://infodot.co.za', 'icon' => 'home', 'active' => true],
                'files' => ['name' => 'Dot.Files', 'url' => 'https://files.infodot.co.za', 'icon' => 'folder', 'active' => true],
                'memory' => ['internal' => true, 'name' => 'Dot.Memory', 'url' => 'https://memory.infodot.co.za', 'icon' => 'memory', 'active' => true],
                'dopemine' => ['internal' => true, 'name' => 'Dot.Dopemine', 'url' => 'https://dopemine.infodot.co.za', 'icon' => 'bolt', 'active' => true],
                'brain' => ['name' => 'Dot.Brain', 'url' => 'https://brain.infodot.co.za', 'icon' => 'psychology', 'active' => true],
                'ehail' => ['name' => 'Dot.Ehail', 'url' => 'https://ehail.infodot.co.za', 'icon' => 'local_taxi', 'active' => false],
            ],
        ]);
    }

    public function test_the_visible_list_leaves_out_every_bridge_service_even_without_the_flag(): void
    {
        $keys = array_keys(Platforms::visible());

        $this->assertContains('files', $keys);
        $this->assertContains('ehail', $keys, 'not-live platforms stay visible as coming soon');
        foreach (['memory', 'dopemine', 'brain'] as $hidden) {
            $this->assertNotContains($hidden, $keys);
            $this->assertTrue(Platforms::isInternal($hidden));
        }
    }

    public function test_no_signed_in_page_names_them(): void
    {
        $user = $this->member();

        foreach (['/home', '/platforms', '/analytics', '/questions', '/people'] as $path) {
            $html = $this->actingAs($user)->get($path)->assertOk()->getContent();

            $this->assertStringContainsString('Dot.Files', $html, $path.' still lists real platforms');
            foreach (['Dot.Memory', 'Dot.Dopemine', 'Dot.Brain', 'memory.infodot', 'dopemine.infodot', 'brain.infodot'] as $name) {
                $this->assertStringNotContainsString($name, $html, "$path shows $name");
            }
        }
    }

    public function test_the_counts_ignore_them(): void
    {
        $html = $this->actingAs($this->member())->get('/platforms')->assertOk()->getContent();

        // files is live, ehail is coming: two visible platforms besides InfoDot, one live.
        $this->assertMatchesRegularExpression('#Live</span><span class="ix-fig"[^>]*>1<#', $html);
        $this->assertMatchesRegularExpression('#Coming</span><span class="ix-fig"[^>]*>1<#', $html);
    }

    public function test_a_hand_made_request_cannot_open_one(): void
    {
        $user = $this->member();

        Livewire::actingAs($user)->test(DotSwitcher::class)->call('switchTo', 'memory')->assertNoRedirect();
        Livewire::actingAs($user)->test(DotSwitcher::class)->call('switchTo', 'dopemine')->assertNoRedirect();
        Livewire::actingAs($user)->test(EcosystemWidget::class)->call('launch', 'memory')->assertNoRedirect();

        Livewire::actingAs($user)->test(DotSwitcher::class)->call('switchTo', 'files')->assertRedirect();
        $this->assertSame(0, $user->tokens()->count(), 'the switcher mints nothing; the launch page does');

        // Even a hand-typed launch URL cannot open a bridge service.
        $this->actingAs($user)->get('/go/memory')->assertNotFound();
        $this->actingAs($user)->get('/go/dopemine')->assertNotFound();
        $this->assertSame(0, $user->tokens()->count());

        $this->actingAs($user)->get('/go/files')->assertOk();
        $this->assertSame(1, $user->tokens()->count(), 'only the Files launch minted a token');
    }

    public function test_the_error_pages_do_not_advertise_them(): void
    {
        $html = $this->get('/no-such-page-anywhere')->assertNotFound()->getContent();

        $this->assertStringContainsString('Dot.Files', $html);
        $this->assertStringNotContainsString('Dot.Memory', $html);
        $this->assertStringNotContainsString('Dot.Dopemine', $html);
    }

    public function test_the_services_can_still_redeem_tokens_so_the_bridge_keeps_working(): void
    {
        $user = $this->member();
        $token = $user->createToken(EcosystemHandoff::tokenName('memory'), ['ecosystem:read'], now()->addMinutes(5))->plainTextToken;

        $this->withHeaders(['Authorization' => 'Bearer memory-secret-for-tests'])
            ->postJson('/api/ecosystem/redeem', ['token' => $token, 'platform' => 'memory'])
            ->assertOk()
            ->assertJsonPath('user.email', $user->email);
    }
}
