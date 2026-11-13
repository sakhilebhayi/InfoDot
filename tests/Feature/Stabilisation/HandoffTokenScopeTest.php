<?php

namespace Tests\Feature\Stabilisation;

use App\Models\User;
use App\Support\EcosystemHandoff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * R-48 S-07. A launch code is posted to a platform, which redeems it server to server. It was stored as an ordinary
 * API token, and InfoDot's own pages accept API tokens (auth:sanctum), so whoever held a code could instead present
 * it to InfoDot as "Authorization: Bearer" and act as the person for its lifetime. A launch code is good for one
 * thing: being redeemed, once, by the platform it names.
 */
class HandoffTokenScopeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'ecosystem.platforms.files' => ['name' => 'Dot.Files', 'url' => 'https://files.infodot.co.za', 'active' => true],
            'ecosystem.service_secrets.files' => 'files-service-secret-for-tests',
        ]);
    }

    private function person(): User
    {
        return User::factory()->withPersonalTeam()->create();
    }

    private function launchCode(User $user, ?string $platform = 'files'): string
    {
        return $user->createToken(EcosystemHandoff::tokenName($platform), ['ecosystem:read'], now()->addMinute())->plainTextToken;
    }

    public function test_a_launch_code_does_not_open_infodots_own_pages(): void
    {
        $code = $this->launchCode($this->person());

        $this->withHeader('Authorization', 'Bearer '.$code)->get('/home')->assertRedirect('/login');
        $this->withHeader('Authorization', 'Bearer '.$code)->getJson('/home')->assertUnauthorized();
        $this->withHeader('Authorization', 'Bearer '.$code)->get('/user/profile/edit')->assertRedirect('/login');
    }

    public function test_a_launch_code_does_not_open_the_api(): void
    {
        $user = $this->person();

        $this->withHeader('Authorization', 'Bearer '.$this->launchCode($user))->getJson('/api/user')->assertUnauthorized();
        $this->withHeader('Authorization', 'Bearer '.$this->launchCode($user, null))->getJson('/api/user')->assertUnauthorized();
        $this->withHeader('Authorization', 'Bearer '.$this->launchCode($user))->postJson('/api/ecosystem/token', ['platform' => 'files'])->assertUnauthorized();
    }

    public function test_a_refused_launch_code_is_not_marked_as_used(): void
    {
        $user = $this->person();
        $code = $this->launchCode($user);

        $this->withHeader('Authorization', 'Bearer '.$code)->getJson('/home')->assertUnauthorized();

        $this->assertNull(PersonalAccessToken::findToken($code)?->last_used_at);
    }

    public function test_the_platform_can_still_redeem_the_code_once(): void
    {
        $user = $this->person();
        $code = $this->launchCode($user);

        $redeem = fn () => $this->withHeader('Authorization', 'Bearer files-service-secret-for-tests')
            ->postJson('/api/ecosystem/redeem', ['token' => $code, 'platform' => 'files']);

        $redeem()->assertOk()->assertJsonPath('user.email', $user->email);
        $redeem()->assertForbidden();
    }

    public function test_an_ordinary_api_token_still_works(): void
    {
        $user = $this->person();
        $token = $user->createToken('integration', ['*'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/user')->assertOk();
    }

    public function test_redeeming_gives_the_platform_the_persons_stable_id(): void
    {
        $user = $this->person();

        $this->withHeader('Authorization', 'Bearer files-service-secret-for-tests')
            ->postJson('/api/ecosystem/redeem', ['token' => $this->launchCode($user), 'platform' => 'files'])
            ->assertOk()
            ->assertJsonPath('user.uuid', $user->uuid)
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('user.email', $user->email);

        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', (string) $user->uuid);
    }
}
