<?php

namespace Tests\Feature\Platforms;

use App\Models\Team;
use App\Support\EcosystemHandoff;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * R-51: `/go/{platform}?company=<team uuid>` opens a platform for a company the person chose, not for InfoDot's current
 * one, and the redeem answer reports that company. Without the parameter nothing changes.
 */
class LaunchForCompanyTest extends PlatformTestCase
{
    private string $serviceKey;

    protected function setUp(): void
    {
        parent::setUp();

        $this->serviceKey = bin2hex(random_bytes(12));
        config(['ecosystem.service_secrets' => ['forms' => $this->serviceKey, 'files' => bin2hex(random_bytes(12))]]);
    }

    /** A person in two companies: A (current) and B. */
    private function twoCompanies(string ...$entitledA): array
    {
        $person = $this->person();
        $a = Team::factory()->create(['user_id' => $person->id, 'personal_team' => false, 'name' => 'Alpha Haulage']);
        $b = Team::factory()->create(['user_id' => $this->person()->id, 'personal_team' => false, 'name' => 'Bravo Logistics']);
        $b->users()->attach($person, ['role' => 'member']);
        $person->forceFill(['current_team_id' => $a->id])->save();

        return [$person->fresh(), $a, $b];
    }

    private function tokenFrom(string $html): string
    {
        preg_match('/name="token" value="([^"]+)"/', $html, $m);
        $this->assertNotEmpty($m[1] ?? null, 'the launch page carries a token');

        return $m[1];
    }

    private function redeem(string $token, string $platform = 'forms')
    {
        return $this->withHeaders(['Authorization' => 'Bearer '.config('ecosystem.service_secrets.'.$platform)])
            ->postJson('/api/ecosystem/redeem', ['token' => $token, 'platform' => $platform]);
    }

    public function test_the_decision_is_made_for_the_chosen_company_and_redeem_reports_it(): void
    {
        [$person, $a, $b] = $this->twoCompanies();
        $this->activatePlatforms($b, 'forms'); // only B holds it; A is current

        $this->actingAs($person)->get('/go/forms')->assertStatus(403); // current company A: refused as before

        $response = $this->actingAs($person)->get('/go/forms?company='.$b->uuid)->assertOk();
        $token = $this->tokenFrom($response->getContent());

        $stored = PersonalAccessToken::findToken($token);
        $this->assertSame(['ecosystem:read', 'company:'.$b->uuid], $stored->abilities);
        $this->assertEqualsWithDelta(60, now()->diffInSeconds($stored->expires_at, false), 2);

        $this->redeem($token)->assertOk()
            ->assertJsonPath('current_team_uuid', $b->uuid)
            ->assertJsonPath('current_team_id', $b->id)
            ->assertJsonPath('teams.0.uuid', fn ($u) => is_string($u))
            ->assertJsonCount(3, 'teams');

        // InfoDot's own current company is not switched.
        $this->assertSame($a->id, $person->fresh()->current_team_id);
    }

    public function test_the_entitled_flags_in_the_answer_are_unchanged_by_the_chosen_company(): void
    {
        [$person, $a, $b] = $this->twoCompanies();
        $this->activatePlatforms($a, 'forms');
        $this->activatePlatforms($b, 'forms');

        $withCompany = $this->redeem($this->tokenFrom($this->actingAs($person)->get('/go/forms?company='.$b->uuid)->getContent()))->assertOk()->json();
        $without = $this->redeem($this->tokenFrom($this->actingAs($person)->get('/go/forms')->getContent()))->assertOk()->json();

        $this->assertSame($without['teams'], $withCompany['teams']);
        $this->assertSame($b->uuid, $withCompany['current_team_uuid']);
        $this->assertSame($a->uuid, $without['current_team_uuid']);
    }

    public function test_without_the_parameter_the_token_and_the_answer_are_exactly_as_before(): void
    {
        [$person, $a, $b] = $this->twoCompanies();
        $this->activatePlatforms($a, 'forms');

        $token = $this->tokenFrom($this->actingAs($person)->get('/go/forms')->assertOk()->getContent());

        $this->assertSame(['ecosystem:read'], PersonalAccessToken::findToken($token)->abilities);

        $json = $this->redeem($token)->assertOk()->json();

        $this->assertSame(['platform', 'user', 'teams', 'current_team_id', 'current_team_uuid'], array_keys($json));
        $this->assertSame(['id', 'uuid', 'name', 'email', 'email_verified'], array_keys($json['user']));
        $this->assertSame($a->id, $json['current_team_id']);
        $this->assertSame($a->uuid, $json['current_team_uuid']);
    }

    public function test_a_company_the_person_is_not_in_is_refused_exactly_like_an_unknown_one(): void
    {
        [$person, $a, $b] = $this->twoCompanies();
        $this->activatePlatforms($a, 'forms');
        $stranger = $this->company('Other Co');
        $this->activatePlatforms($stranger, 'forms');

        $real = $this->actingAs($person)->get('/go/forms?company='.$stranger->uuid);
        $unknown = $this->actingAs($person)->get('/go/forms?company='.(string) Str::uuid());
        $junk = $this->actingAs($person)->get('/go/forms?company[]=x');

        foreach ([$real, $unknown, $junk] as $r) {
            $r->assertStatus(403)->assertSee('You are not in that company')->assertDontSee('Other Co')->assertDontSee($stranger->uuid);
        }

        $this->assertSame($this->strip($real->getContent()), $this->strip($unknown->getContent()));
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_a_platform_the_chosen_company_does_not_hold_is_refused_even_when_the_current_one_does(): void
    {
        [$person, $a, $b] = $this->twoCompanies();
        $this->activatePlatforms($a, 'forms');

        $this->actingAs($person)->get('/go/forms?company='.$b->uuid)->assertStatus(403)->assertSee('Bravo Logistics has not switched on Dot.Forms');
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_a_personal_workspace_holds_no_company_platform(): void
    {
        $person = $this->person();
        $personal = $person->currentTeam;

        $this->actingAs($person)->get('/go/forms?company='.$personal->uuid)->assertStatus(403)->assertSee('is for companies');
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_a_suspended_person_gets_no_code_with_or_without_a_company(): void
    {
        [$person, $a, $b] = $this->twoCompanies();
        $this->activatePlatforms($b, 'forms');
        $person->forceFill(['suspended_at' => now(), 'suspended_reason' => 'test'])->save();

        $this->actingAs($person)->get('/go/forms?company='.$b->uuid)->assertRedirect(); // the suspension middleware turns the person away first
        $this->assertNull(EcosystemHandoff::launchForm($person, 'forms', null, $b));
        $this->assertSame(0, DB::table('personal_access_tokens')->count());
    }

    public function test_tampered_expired_reused_and_wrong_platform_tokens_are_still_refused(): void
    {
        [$person, $a, $b] = $this->twoCompanies();
        $this->activatePlatforms($b, 'forms');
        $token = $this->tokenFrom($this->actingAs($person)->get('/go/forms?company='.$b->uuid)->getContent());

        $this->redeem($token.'x')->assertStatus(403)->assertExactJson(['error' => 'invalid_request']);
        $this->redeem($token, 'files')->assertStatus(403);

        $this->redeem($token)->assertOk();
        $this->redeem($token)->assertStatus(403)->assertExactJson(['error' => 'invalid_request']); // single use

        $expired = $person->createToken(EcosystemHandoff::tokenName('forms'), ['ecosystem:read', 'company:'.$b->uuid], now()->subSecond())->plainTextToken;
        $this->redeem($expired)->assertStatus(403);
    }

    public function test_a_company_left_or_not_entitled_before_redeem_is_refused_not_swapped(): void
    {
        [$person, $a, $b] = $this->twoCompanies();
        $this->activatePlatforms($a, 'forms');
        $this->activatePlatforms($b, 'forms');

        $token = $this->tokenFrom($this->actingAs($person)->get('/go/forms?company='.$b->uuid)->getContent());
        $b->users()->detach($person);
        $this->redeem($token)->assertStatus(403)->assertExactJson(['error' => 'invalid_request']);

        // A hand-made token naming a company that is not the person's is refused too.
        $forged = $person->createToken(EcosystemHandoff::tokenName('forms'), ['ecosystem:read', 'company:'.(string) Str::uuid()], now()->addMinute())->plainTextToken;
        $this->redeem($forged)->assertStatus(403);
    }

    private function strip(string $html): string
    {
        return (string) preg_replace('/(_token|nonce)[^>]*>|value="[^"]*"/', '', $html);
    }
}
