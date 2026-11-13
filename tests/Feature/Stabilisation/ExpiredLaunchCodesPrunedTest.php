<?php

namespace Tests\Feature\Stabilisation;

use App\Models\User;
use App\Support\EcosystemHandoff;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * R-49. A launch code that is never redeemed was never removed: the live site held 31 expired ones. They cannot be
 * used (expiry is checked, and launch codes are refused as logins), but dead credentials should not pile up.
 */
class ExpiredLaunchCodesPrunedTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_launch_codes_are_removed_and_live_ones_are_kept(): void
    {
        $user = User::factory()->withPersonalTeam()->create();
        $user->createToken(EcosystemHandoff::tokenName('files'), ['ecosystem:read'], now()->subHours(3));
        $user->createToken(EcosystemHandoff::tokenName('files'), ['ecosystem:read'], now()->addMinute());

        $this->artisan('sanctum:prune-expired --hours=1')->assertSuccessful();

        $this->assertSame(1, DB::table('personal_access_tokens')->count());
        $this->assertTrue(DB::table('personal_access_tokens')->where('expires_at', '>', now())->exists());
    }

    public function test_the_clean_up_is_scheduled(): void
    {
        $commands = collect(app(Schedule::class)->events())->map(fn ($event) => (string) $event->command);

        $this->assertTrue($commands->contains(fn ($command) => str_contains($command, 'sanctum:prune-expired --hours=1')));
    }
}
