<?php

namespace Tests\Feature\Stabilisation;

use App\Models\User;
use App\Support\EcosystemHandoff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * R-48 S-16. Two endpoints were left from the first sign-in design, in which platforms shared InfoDot's database:
 * one minted a launch code for any holder of an access token, the other turned a code into an InfoDot session with
 * no second factor. No platform uses either (all redeem server to server), and since launch codes stopped being
 * accepted as logins nothing can reach the first. They are retired. The old address still answers, so a stale link
 * lands on the sign-in page.
 */
class LegacySignInRetiredTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_code_posted_to_infodots_old_address_signs_nobody_in(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $code = $user->createToken(EcosystemHandoff::tokenName(null), ['ecosystem:read'], now()->addMinute())->plainTextToken;

        $this->post('/auth/ecosystem', ['token' => $code], ['Origin' => 'http://files.localhost'])->assertRedirect('/login');
        $this->get('/auth/ecosystem?token='.$code)->assertRedirect('/login');

        $this->assertGuest();
        $this->assertSame(1, DB::table('personal_access_tokens')->count(), 'the code is not consumed: nothing here reads it any more');
    }

    public function test_launch_codes_can_no_longer_be_minted_over_the_api(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $token = $user->createToken('integration', ['*'])->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)->postJson('/api/ecosystem/token', [])->assertNotFound();
        $this->assertFalse(Route::has('api.ecosystem.token'));
    }

    public function test_opening_a_platform_still_issues_a_code_for_that_platform_only(): void
    {
        config(['ecosystem.platforms.files' => ['name' => 'Dot.Files', 'url' => 'https://files.infodot.co.za', 'active' => true]]);
        $user = User::factory()->withPersonalTeam()->create();

        $form = EcosystemHandoff::launchForm($user, 'files');

        $this->assertNotNull($form);
        $this->assertSame('https://files.infodot.co.za/auth/ecosystem', $form['action']);
        $this->assertSame(EcosystemHandoff::tokenName('files'), DB::table('personal_access_tokens')->value('name'));
    }
}
