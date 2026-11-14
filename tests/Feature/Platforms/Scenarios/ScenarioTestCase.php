<?php

namespace Tests\Feature\Platforms\Scenarios;

use App\Models\PlatformEntitlement;
use App\Models\PlatformPlan;
use App\Models\Team;
use App\Models\User;
use App\Support\EcosystemHandoff;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Platforms\AdminCentreTestCase;

/**
 * The ground the twenty scenarios of R-50 stand on (docs/programme/R50_SCENARIOS.md). They drive InfoDot's real routes
 * and services against a real (SQLite) database. Nothing of InfoDot's own is faked. Other platforms are never called:
 * a scenario that ends inside another platform, or at a real payment, says so with markTestIncomplete.
 *
 * Where a payment is needed, the test sends InfoDot the signed confirmation Dot.Finance will send. That exercises
 * InfoDot's side completely, but the signature is made by the test with a key made for it, so it proves nothing about
 * Dot.Finance, which has no payment code yet. Those scenarios end in markTestIncomplete for that reason.
 */
abstract class ScenarioTestCase extends AdminCentreTestCase
{
    protected const PAYMENT_NOT_BUILT = 'A real payment needs Dot.Finance, which has no checkout or provider yet: the confirmation here is signed by the test, so only InfoDot\'s side is proved.';

    protected function setUp(): void
    {
        parent::setUp();

        // Each platform proves itself with its own secret; made for this test, never written in the file.
        $secrets = [];
        foreach (['forms', 'hr', 'projects', 'sheet', 'files'] as $key) {
            $secrets[$key] = Str::random(40);
        }
        config(['ecosystem.service_secrets' => $secrets]);
    }

    /** Open a platform as a person would: the launch page. 200 is a page that posts a launch code, 403 is the explanation. */
    protected function open(User $person, string $platform): TestResponse
    {
        return $this->actingAs($person->fresh())->get('/go/'.$platform);
    }

    protected function assertCanOpen(User $person, string $platform, string $why = ''): void
    {
        $this->open($person, $platform)->assertOk()->assertSee(EcosystemHandoff::endpointFor(config('ecosystem.platforms.'.$platform)), false);
        $this->assertTrue($this->access()->decide($person->fresh(), $platform)->allowed, $why ?: "{$person->name} can open {$platform}");
    }

    protected function assertCannotOpen(User $person, string $platform, string $reason): void
    {
        $this->open($person, $platform)->assertForbidden()->assertDontSee('/auth/ecosystem', false);
        $this->assertSame($reason, $this->access()->decide($person->fresh(), $platform)->reason, "{$person->name} and {$platform}");
    }

    /** What a platform learns when the person arrives with a launch code: the answer for each of their companies. */
    protected function redeemFor(User $person, string $platform): TestResponse
    {
        $token = $person->fresh()->createToken(EcosystemHandoff::tokenName($platform), ['ecosystem:read'], now()->addMinute())->plainTextToken;

        return $this->withHeaders(['Authorization' => 'Bearer '.config('ecosystem.service_secrets.'.$platform)])->postJson('/api/ecosystem/redeem', ['token' => $token, 'platform' => $platform]);
    }

    protected function switchOn(Team $team, User $by, string $platform): TestResponse
    {
        return $this->actingAs($by->fresh())->post('/platforms/'.$platform.'/switch-on', ['company' => $team->uuid]);
    }

    protected function held(Team $team, string $platform): ?PlatformEntitlement
    {
        return PlatformEntitlement::where('team_id', $team->id)->where('platform_key', $platform)->first();
    }

    /** @return list<string> the types of the history of one platform for one company, oldest first */
    protected function history(Team $team, string $platform): array
    {
        return DB::table('platform_entitlement_events')->where('team_id', $team->id)->where('platform_key', $platform)->orderBy('id')->pluck('type')->all();
    }

    /** What the platform was told last, from the outbox. @return array<string, mixed> */
    protected function lastTold(Team $team, string $platform): array
    {
        return (array) json_decode((string) DB::table('entitlement_outbox')->where('team_uuid', $team->uuid)->where('platform_key', $platform)->orderByDesc('id')->value('payload'), true);
    }

    /** A paid subscription the way it happens: the owner asks, then Dot.Finance's signed confirmation arrives. */
    protected function paidSubscription(Team $team, string $platform = 'hr', string $key = ''): void
    {
        $plan = PlatformPlan::where('platform_key', $platform)->where('active', true)->orderBy('id')->firstOrFail();
        $this->entitlements()->subscribe($team, $platform, $plan, $team->owner);
        $this->deliver($this->paymentEvent($team, 'paid', ['platform' => $platform, 'plan' => $plan->slug]), $key ?: $this->financeKey())->assertOk()->assertExactJson(['status' => 'applied']);
    }
}
