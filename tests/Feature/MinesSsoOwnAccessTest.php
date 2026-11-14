<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use App\Services\Platforms\AccessDecision;
use App\Services\Platforms\PlatformAccess;
use App\Support\EcosystemHandoff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * R-52: Dot.Mines can receive a sign-in token (DOT_MINES_SSO) without InfoDot enforcing a company entitlement for it
 * (registry 'access' => 'own'): personal workspaces and Paystack-billed Mines teams keep working, and the flag is off
 * by default so deploying InfoDot changes nothing until the owner turns it on.
 */
class MinesSsoOwnAccessTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'mines-service-secret-for-tests';

    private function person(bool $personal = true): array
    {
        $user = User::factory()->create(['email_verified_at' => Carbon::now()]);
        $team = Team::factory()->create(['user_id' => $user->id, 'personal_team' => $personal, 'name' => 'Pit Co']);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return [$user, $team];
    }

    private function ssoOn(): void
    {
        config([
            'ecosystem.platforms.mines.sso' => true,
            'ecosystem.platforms.mines.url' => 'https://mines.infodot.co.za',
            'ecosystem.platforms.mines.active' => true,
            'ecosystem.service_secrets.mines' => self::SECRET,
        ]);
    }

    public function test_the_shipped_registry_has_mines_sso_off_and_own_access(): void
    {
        $this->assertFalse(config('ecosystem.platforms.mines.sso'));
        $this->assertSame('own', config('ecosystem.platforms.mines.access'));
        $this->assertTrue(EcosystemHandoff::hasNoReceiver('mines'));
        $this->assertTrue(EcosystemHandoff::managesOwnAccess('mines'));
        // Charts is unchanged: no receiver, no flag.
        $this->assertTrue(EcosystemHandoff::hasNoReceiver('charts'));
        $this->assertNull(config('ecosystem.platforms.charts.access'));
    }

    public function test_with_the_flag_off_go_mines_is_the_plain_redirect_and_no_token_exists(): void
    {
        [$user] = $this->person();
        config(['ecosystem.platforms.mines.url' => 'https://mines.infodot.co.za']);

        $this->actingAs($user)->get('/go/mines')->assertRedirect('https://mines.infodot.co.za/');
        $this->assertSame('https://mines.infodot.co.za/', EcosystemHandoff::launchUrl($user, 'mines'));
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_with_the_flag_on_go_mines_mints_a_token_and_posts_it_to_the_receiver_form(): void
    {
        $this->ssoOn();
        [$user] = $this->person();

        $response = $this->actingAs($user)->get('/go/mines');

        $response->assertOk();
        $html = $response->getContent();
        $this->assertMatchesRegularExpression('#<form id="launch" method="post" action="https://mines\.infodot\.co\.za/auth/ecosystem">#', $html);
        $this->assertStringContainsString('<input type="hidden" name="token" value="', $html);
        $this->assertStringNotContainsString('?token=', $html);
        $this->assertNull($response->headers->get('Location'));
        $this->assertStringContainsString('form-action https://mines.infodot.co.za', $response->headers->get('Content-Security-Policy'));
        $this->assertSame(1, DB::table('personal_access_tokens')->where('name', 'ecosystem-handoff:mines')->count());
    }

    public function test_the_switcher_url_with_the_flag_on_is_the_launch_route_and_carries_no_token(): void
    {
        $this->ssoOn();
        [$user] = $this->person();

        $url = EcosystemHandoff::launchUrl($user, 'mines');

        $this->assertStringContainsString('/go/mines', $url);
        $this->assertStringNotContainsString('token', $url);
    }

    public function test_the_company_parameter_still_works_for_mines_with_the_flag_on(): void
    {
        $this->ssoOn();
        [$user, $team] = $this->person(false);

        $html = $this->actingAs($user)->get('/go/mines?company='.$team->uuid)->assertOk()->getContent();
        $this->assertStringContainsString('action="https://mines.infodot.co.za/auth/ecosystem"', $html);

        // A company that is not the person's is refused, as for every platform.
        $other = Team::factory()->create(['personal_team' => false]);
        $this->actingAs($user)->get('/go/mines?company='.$other->uuid)->assertStatus(403);
    }

    public function test_access_never_refuses_mines_for_a_personal_workspace_or_a_company_that_never_activated_it(): void
    {
        $access = app(PlatformAccess::class);

        foreach ([false, true] as $flag) {
            config(['ecosystem.platforms.mines.sso' => $flag]);
            [$user, $personal] = $this->person(true);
            [$owner, $company] = $this->person(false);

            foreach ([[$user, $personal], [$owner, $company]] as [$u, $t]) {
                $decision = $access->decideFor($u, $t, 'mines');
                $this->assertTrue($decision->allowed, 'flag '.var_export($flag, true));
                $this->assertSame(AccessDecision::MANAGED_IN_PLATFORM, $decision->reason);
            }
            $this->assertTrue($access->decide($user, 'mines')->allowed);
        }
    }

    public function test_other_platforms_are_still_enforced_for_a_personal_workspace(): void
    {
        $this->ssoOn();
        [$user, $personal] = $this->person(true);

        $this->assertFalse(EcosystemHandoff::managesOwnAccess('central'));
        $this->assertFalse(EcosystemHandoff::managesOwnAccess('files'));
        $this->assertTrue(app(PlatformAccess::class)->decideFor($user, $personal, 'central')->refused());
    }

    public function test_mines_redeems_with_its_own_secret_for_a_personal_workspace_and_a_wrong_secret_is_refused(): void
    {
        $this->ssoOn();
        [$user, $personal] = $this->person(true);
        $mint = fn () => $user->createToken(EcosystemHandoff::tokenName('mines'), ['ecosystem:read'], now()->addMinutes(5))->plainTextToken;
        $call = fn (string $token, ?string $secret) => $this->withHeaders($secret === null ? [] : ['Authorization' => 'Bearer '.$secret])
            ->postJson('/api/ecosystem/redeem', ['token' => $token, 'platform' => 'mines']);

        $token = $mint();
        $call($token, 'wrong')->assertUnauthorized();
        $call($token, null)->assertUnauthorized();
        // Neither refusal spent the token.
        $call($token, self::SECRET)->assertOk()
            ->assertJsonPath('platform', 'mines')
            ->assertJsonPath('user.uuid', $user->uuid)
            ->assertJsonPath('teams.0.uuid', $personal->uuid)
            ->assertJsonPath('teams.0.entitled', true)
            ->assertJsonPath('teams.0.personal', true);
        // Single use.
        $call($token, self::SECRET)->assertForbidden();

        // A token minted for another platform is not a Mines token.
        $files = $user->createToken(EcosystemHandoff::tokenName('files'), ['ecosystem:read'], now()->addMinutes(5))->plainTextToken;
        $call($files, self::SECRET)->assertForbidden();
    }

    public function test_the_redeem_log_says_whether_the_secret_is_unconfigured_or_wrong_and_never_logs_the_token_or_secret(): void
    {
        $this->ssoOn();
        [$user] = $this->person();
        $token = $user->createToken(EcosystemHandoff::tokenName('mines'), ['ecosystem:read'], now()->addMinutes(5))->plainTextToken;
        Log::spy();

        $this->withHeaders(['Authorization' => 'Bearer nope-nope'])->postJson('/api/ecosystem/redeem', ['token' => $token, 'platform' => 'mines'])->assertUnauthorized();
        Log::shouldHaveReceived('warning')->withArgs(fn ($m, $ctx = []) => $m === 'Ecosystem redeem refused.'
            && $ctx['reason'] === 'service secret wrong or missing in the call'
            && ! str_contains(json_encode($ctx), $token) && ! str_contains(json_encode($ctx), self::SECRET))->once();

        config(['ecosystem.service_secrets' => []]);
        $this->withHeaders(['Authorization' => 'Bearer nope-nope'])->postJson('/api/ecosystem/redeem', ['token' => $token, 'platform' => 'mines'])->assertUnauthorized();
        Log::shouldHaveReceived('warning')->withArgs(fn ($m, $ctx = []) => ($ctx['reason'] ?? '') === 'service secret not configured on InfoDot (ECOSYSTEM_SECRET_MINES empty)')->once();
    }
}
