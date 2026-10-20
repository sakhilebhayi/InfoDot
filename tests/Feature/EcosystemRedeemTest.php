<?php

namespace Tests\Feature;

use App\Livewire\DotSwitcher;
use App\Livewire\EcosystemWidget;
use App\Models\Team;
use App\Models\User;
use App\Support\EcosystemHandoff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class EcosystemRedeemTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'files-service-secret-for-tests';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ecosystem.service_secrets' => ['files' => self::SECRET, 'docs' => 'docs-service-secret-for-tests'],
            'ecosystem.platforms' => [
                'files' => ['name' => 'Dot.Files', 'url' => 'https://files.infodot.co.za', 'icon' => 'folder', 'active' => true],
                'docs' => ['name' => 'Dot.docs', 'url' => 'https://doc.infodot.co.za', 'icon' => 'description', 'active' => true],
                'hr' => ['name' => 'Dot.HR', 'url' => 'https://hr.infodot.co.za', 'icon' => 'badge', 'active' => false],
            ],
        ]);
    }

    private function member(array $attrs = []): User
    {
        $user = User::factory()->create(array_merge(['email_verified_at' => Carbon::now()], $attrs));
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => true, 'name' => 'Amara Team']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user;
    }

    private function mint(User $user, ?string $platform, int $minutes = 5, array $abilities = ['ecosystem:read']): string
    {
        return $user->createToken(EcosystemHandoff::tokenName($platform), $abilities, now()->addMinutes($minutes))->plainTextToken;
    }

    private function redeem(string $token, string $platform = 'files', ?string $secret = self::SECRET)
    {
        return $this->withHeaders($secret === null ? [] : ['Authorization' => 'Bearer '.$secret])
            ->postJson('/api/ecosystem/redeem', ['token' => $token, 'platform' => $platform]);
    }

    public function test_a_satellite_redeems_a_token_minted_for_it_and_learns_who_the_person_is(): void
    {
        $user = $this->member(['name' => 'Amara Dlamini', 'email' => 'amara@example.test']);

        $this->redeem($this->mint($user, 'files'))
            ->assertOk()
            ->assertJsonPath('user.email', 'amara@example.test')
            ->assertJsonPath('user.name', 'Amara Dlamini')
            ->assertJsonPath('user.email_verified', true)
            ->assertJsonPath('teams.0.name', 'Amara Team')
            ->assertJsonPath('teams.0.role', 'owner')
            ->assertJsonPath('teams.0.personal', true)
            ->assertJsonPath('teams.0.uuid', $user->currentTeam->uuid)
            ->assertJsonPath('current_team_id', $user->current_team_id)
            ->assertJsonPath('current_team_uuid', $user->currentTeam->uuid);
    }

    public function test_the_payload_carries_no_password_or_token_material(): void
    {
        $body = $this->redeem($this->mint($this->member(), 'files'))->assertOk()->getContent();

        foreach (['password', 'remember_token', 'two_factor', 'ecosystem-handoff'] as $secret) {
            $this->assertStringNotContainsString($secret, $body);
        }
    }

    public function test_a_token_works_once(): void
    {
        $token = $this->mint($this->member(), 'files');

        $this->redeem($token)->assertOk();
        $this->redeem($token)->assertForbidden();
    }

    public function test_a_token_minted_for_another_platform_is_refused_without_being_burned(): void
    {
        $user = $this->member();
        $token = $this->mint($user, 'docs');

        $this->redeem($token, 'files')->assertForbidden();
        // The refused attempt did not burn the other platform token: a refusal
        // for a wrong audience happens before deletion.
        $this->redeem($token, 'docs', 'docs-service-secret-for-tests')->assertOk();
    }

    public function test_a_token_with_no_audience_cannot_be_redeemed_remotely(): void
    {
        $this->redeem($this->mint($this->member(), null))->assertForbidden();
    }

    public function test_the_service_secret_is_required_and_must_match_the_platform(): void
    {
        $token = $this->mint($this->member(), 'files');

        $this->redeem($token, 'files', null)->assertUnauthorized();
        $this->redeem($token, 'files', 'wrong')->assertUnauthorized();
        $this->redeem($token, 'files', 'docs-service-secret-for-tests')->assertUnauthorized();
        // None of those consumed the token.
        $this->redeem($token)->assertOk();
    }

    public function test_a_platform_with_no_secret_or_not_live_cannot_redeem(): void
    {
        $user = $this->member();
        config(['ecosystem.service_secrets' => ['hr' => 'hr-secret']]);

        $this->redeem($this->mint($user, 'hr'), 'hr', 'hr-secret')->assertForbidden();
        $this->redeem($this->mint($user, 'files'), 'files', self::SECRET)->assertUnauthorized();
    }

    public function test_expired_tokens_and_tokens_without_the_ability_are_refused(): void
    {
        $user = $this->member();

        $this->redeem($this->mint($user, 'files', -1))->assertForbidden();
        $this->redeem($this->mint($user, 'files', 5, ['something:else']))->assertForbidden();
    }

    public function test_an_unverified_email_is_refused(): void
    {
        $user = $this->member(['email_verified_at' => null]);

        $this->redeem($this->mint($user, 'files'))->assertForbidden();
    }

    public function test_garbage_and_missing_input_is_rejected(): void
    {
        $this->redeem('not-a-real-token')->assertForbidden();
        $this->withHeaders(['Authorization' => 'Bearer '.self::SECRET])->postJson('/api/ecosystem/redeem', [])->assertStatus(422);
    }

    public function test_the_launch_page_mints_tokens_bound_to_the_platform_it_opens(): void
    {
        $this->assertSame('ecosystem-handoff:files', EcosystemHandoff::tokenName('files'));
        $this->assertSame('ecosystem-handoff', EcosystemHandoff::tokenName(null));

        $user = $this->member();
        config(['ecosystem.trusted_domains' => ['infodot.co.za']]);

        // The switcher and the widget only point the browser at the launch page; the token is minted there.
        Livewire::actingAs($user)->test(DotSwitcher::class)->call('switchTo', 'files')->assertRedirect(route('platform.launch', ['platform' => 'files']));
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);

        $this->actingAs($user)->get('/go/files')->assertOk();
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'ecosystem-handoff:files', 'tokenable_id' => $user->id]);

        $this->actingAs($user)->get('/go/docs')->assertOk();
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'ecosystem-handoff:docs', 'tokenable_id' => $user->id]);
    }

    public function test_the_api_token_endpoint_accepts_a_registered_platform_only(): void
    {
        $this->actingAs($this->member());

        $this->postJson('/api/ecosystem/token', ['platform' => 'files'])->assertOk();
        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'ecosystem-handoff:files']);
        $this->postJson('/api/ecosystem/token', ['platform' => 'not-a-platform'])->assertStatus(422);
    }
}
