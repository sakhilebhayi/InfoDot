<?php

namespace Tests\Feature\Platforms;

use App\Models\PlatformCatalogue;
use App\Models\PlatformPlan;
use App\Models\Team;
use App\Models\User;
use App\Services\Platforms\CompanyCatalogue;
use App\Services\Platforms\PlatformRecommendations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Recommendations (R-50 section 8): rules in config/platform_recommendations.php, tested only against what InfoDot's
 * own records say about the company. Each one shows its reason. With nothing true to say, nothing is shown.
 */
class PlatformRecommendationsTest extends PlatformTestCase
{
    private function suggested(Team $team, ?User $as = null): array
    {
        $as ??= $team->owner;

        return app(PlatformRecommendations::class)->for($as->fresh(), $team->fresh(), new CompanyCatalogue)->pluck('reason', 'key')->all();
    }

    private function people(Team $team, int $n): void
    {
        foreach (range(1, $n) as $i) {
            $this->memberOf($team);
        }
    }

    public function test_a_company_with_nothing_true_to_say_is_shown_no_recommendation(): void
    {
        $team = $this->company();

        $this->assertSame([], $this->suggested($team));
        $html = $this->actingAs($team->owner)->get('/platforms')->assertOk()->getContent();
        $this->assertStringNotContainsString('Suggested for', $html);
        $this->assertStringNotContainsString('data-recommendation', $html);
    }

    public function test_the_facts_are_counted_from_the_companys_own_records_and_nobody_elses(): void
    {
        $team = $this->company();
        $other = $this->company('Kestrel Mining');
        $this->people($team, 2);
        $this->people($other, 6);
        DB::table('business_services')->insert([
            ['team_id' => $team->id, 'name' => 'Pallet', 'kind' => 'product', 'sort' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['team_id' => $team->id, 'name' => 'Delivery', 'kind' => 'service', 'sort' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['team_id' => $other->id, 'name' => 'Ore', 'kind' => 'product', 'sort' => 1, 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('business_projects')->insert(['team_id' => $other->id, 'title' => 'Shaft 4', 'sort' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $team->forceFill(['industry' => 'logistics'])->save();
        $other->forceFill(['industry' => 'not-a-real-industry'])->save();

        $facts = app(PlatformRecommendations::class)->facts($team->fresh());
        $this->assertSame(['industry' => 'logistics', 'members' => 3, 'enquiries' => 0, 'services' => 1, 'products' => 1, 'showcase_projects' => 0, 'questions' => 0, 'solutions' => 0], $facts);

        $theirs = app(PlatformRecommendations::class)->facts($other->fresh());
        $this->assertNull($theirs['industry'], 'an industry InfoDot does not know is not a fact');
        $this->assertSame([7, 1, 1], [$theirs['members'], $theirs['products'], $theirs['showcase_projects']]);
    }

    public function test_each_shipped_rule_fires_on_its_fact_and_says_why(): void
    {
        // Five people: Dot.HR.
        $team = $this->company('Northwind Freight');
        $this->people($team, 3);
        $this->assertArrayNotHasKey('hr', $this->suggested($team), 'four people is below the rule');
        $this->people($team, 1);
        $this->assertSame('Northwind Freight has 5 people on InfoDot. Dot.HR keeps their records, leave and timesheets in one place.', $this->suggested($team)['hr']);

        // Products on the business page: Dot.Emall. Services alone do not count.
        $shop = $this->company('Corner Supply');
        DB::table('business_services')->insert(['team_id' => $shop->id, 'name' => 'Advice', 'kind' => 'service', 'sort' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame([], $this->suggested($shop));
        DB::table('business_services')->insert(['team_id' => $shop->id, 'name' => 'Crates', 'kind' => 'product', 'sort' => 2, 'created_at' => now(), 'updated_at' => now()]);
        $this->assertStringContainsString('Corner Supply lists products for sale on its business page (1 so far).', $this->suggested($shop)['emall']);

        // Showcase projects need a second person too.
        $builder = $this->company('Ridge Build');
        DB::table('business_projects')->insert(['team_id' => $builder->id, 'title' => 'Warehouse', 'sort' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame([], $this->suggested($builder), 'one condition of two is not enough');
        $this->people($builder, 1);
        $this->assertStringContainsString('(1 so far) and has 2 people', $this->suggested($builder)['projects']);

        // The industry the company chose.
        $mine = $this->company('Kestrel Mining');
        $mine->forceFill(['industry' => 'mining'])->save();
        $this->assertSame('Kestrel Mining is registered on InfoDot as a mining and quarrying business. Dot.Central is the control room for a mining shift.', $this->suggested($mine)['central']);

        // Three enquiries in the InfoDot Inbox.
        $firm = $this->company('Harbour Legal');
        $customer = $this->person();
        foreach (range(1, 3) as $i) {
            DB::table('enquiries')->insert(['uuid' => (string) Str::uuid(), 'team_id' => $firm->id, 'user_id' => $customer->id, 'subject' => "Quote {$i}", 'category' => 'general', 'status' => 'new', 'created_at' => now(), 'updated_at' => now()]);
            $this->assertSame($i === 3, array_key_exists('engage', $this->suggested($firm)), "after {$i} enquiries");
        }
        $this->assertStringContainsString('has received 3 enquiries in its InfoDot Inbox', $this->suggested($firm)['engage']);
    }

    public function test_a_platform_the_company_holds_or_cannot_get_is_never_recommended(): void
    {
        $team = $this->company();
        $this->people($team, 5);
        $this->assertArrayHasKey('hr', $this->suggested($team));

        $this->activatePlatforms($team, 'hr');
        $this->assertArrayNotHasKey('hr', $this->suggested($team), 'already held');

        $mine = $this->company('Kestrel Mining');
        $mine->forceFill(['industry' => 'mining'])->save();
        PlatformCatalogue::where('key', 'central')->update(['operational_status' => 'maintenance']);
        $this->assertSame([], $this->suggested($mine), 'a platform that is unavailable is not suggested');

        // A personal workspace cannot switch anything on, so it is told nothing.
        $alone = $this->person();
        $this->assertSame([], app(PlatformRecommendations::class)->for($alone, $alone->currentTeam, new CompanyCatalogue)->all());
    }

    public function test_a_rule_without_a_real_condition_never_fires(): void
    {
        $team = $this->company();
        $this->people($team, 6);
        $recommendations = app(PlatformRecommendations::class);
        $facts = $recommendations->facts($team->fresh()) + ['holds' => ['files']];

        $this->assertFalse($recommendations->holds([], $facts), 'no condition');
        $this->assertFalse($recommendations->holds(['revenue_at_least' => 1], $facts), 'a condition InfoDot has no fact for');
        $this->assertFalse($recommendations->holds(['min' => ['revenue' => 1]], $facts), 'a count InfoDot does not keep');
        $this->assertFalse($recommendations->holds(['min' => []], $facts));
        $this->assertFalse($recommendations->holds(['industry' => ['mining']], $facts), 'the company chose no industry');
        $this->assertTrue($recommendations->holds(['min' => ['members' => 0]], $facts) === $recommendations->holds(['min' => ['members' => 1]], $facts), 'a threshold of nothing is read as at least one');
        $this->assertTrue($recommendations->holds(['min' => ['members' => 7], 'holds' => ['files']], $facts));
        $this->assertFalse($recommendations->holds(['min' => ['members' => 8]], $facts));
        $this->assertFalse($recommendations->holds(['holds' => ['hr']], $facts));

        config(['platform_recommendations.rules' => [
            ['platform' => 'forms', 'when' => [], 'reason' => 'Everyone needs forms.'],
            ['platform' => 'hr', 'when' => ['guess' => true], 'reason' => 'You probably need this.'],
            ['platform' => 'projects', 'when' => ['min' => ['members' => 2]], 'reason' => ''],
            ['platform' => 'not-a-platform', 'when' => ['min' => ['members' => 2]], 'reason' => 'Nothing.'],
        ]]);
        $this->assertSame([], $this->suggested($team));

        config(['platform_recommendations.rules' => []]);
        $this->assertSame([], $this->suggested($team));
    }

    public function test_every_shipped_rule_names_a_real_platform_and_only_known_conditions(): void
    {
        $rules = config('platform_recommendations.rules');
        $this->assertNotEmpty($rules);

        foreach ($rules as $rule) {
            $this->assertNotNull(PlatformCatalogue::where('key', $rule['platform'])->where('discoverable', true)->first(), $rule['platform']);
            $this->assertNotSame('', trim($rule['reason']));
            $this->assertNotEmpty($rule['when']);
            $this->assertSame([], array_diff(array_keys($rule['when']), ['industry', 'min', 'holds']));
            $this->assertSame([], array_diff(array_keys($rule['when']['min'] ?? []), PlatformRecommendations::COUNTED));
            foreach ($rule['when']['industry'] ?? [] as $industry) {
                $this->assertArrayHasKey($industry, config('industries'));
            }
            $this->assertDoesNotMatchRegularExpression('/R\s?\d|\bZAR\b|%|\bsave\b/i', $rule['reason'], 'a reason states a fact, never a price or a promise');
        }
    }

    public function test_the_owner_sees_the_reason_and_can_dismiss_it_for_the_whole_company(): void
    {
        $team = $this->company('Northwind Freight');
        $admin = $this->memberOf($team, 'admin');
        $member = $this->memberOf($team);
        $this->people($team, 2);

        $html = $this->actingAs($team->owner)->get('/platforms')->assertOk()->getContent();
        $this->assertStringContainsString('Suggested for Northwind Freight', $html);
        $this->assertStringContainsString('data-recommendation="hr"', $html);
        $this->assertStringContainsString('Northwind Freight has 5 people on InfoDot.', $html);

        // Suggestions are for the people who can act on them.
        $this->assertStringNotContainsString('Suggested for', $this->actingAs($member)->get('/platforms')->assertOk()->getContent());
        $this->actingAs($member)->post('/platforms/hr/recommendation/dismiss', ['company' => $team->uuid])->assertForbidden();
        $this->assertSame(0, DB::table('platform_recommendation_dismissals')->count());

        // Another company cannot dismiss ours.
        $other = $this->company('Kestrel Mining');
        $this->actingAs($other->owner)->post('/platforms/hr/recommendation/dismiss', ['company' => $team->uuid])->assertStatus(409);
        $this->assertSame(0, DB::table('platform_recommendation_dismissals')->count());

        $this->actingAs($admin)->post('/platforms/hr/recommendation/dismiss', ['company' => $team->uuid])
            ->assertRedirect(route('ecosystem.widget'))->assertSessionHas('status');
        $this->actingAs($admin)->post('/platforms/hr/recommendation/dismiss', ['company' => $team->uuid])->assertRedirect();
        $this->assertSame([[$team->id, 'hr', $admin->id]], DB::table('platform_recommendation_dismissals')->get(['team_id', 'platform_key', 'user_id'])->map(fn ($row) => [(int) $row->team_id, $row->platform_key, (int) $row->user_id])->all());

        // Gone for the owner too, and it stays gone; the platform itself is still in the catalogue.
        $after = $this->actingAs($team->owner)->get('/platforms')->assertOk()->getContent();
        $this->assertStringNotContainsString('data-recommendation="hr"', $after);
        $this->assertStringContainsString('data-platform="hr"', $after);
        $this->assertArrayNotHasKey('hr', $this->suggested($team));
    }

    public function test_browsing_works_with_recommendations_switched_off_and_at_most_three_are_shown(): void
    {
        $team = $this->company();
        $this->people($team, 5);

        config(['platform_recommendations.rules' => collect(['hr', 'forms', 'projects', 'sheet', 'notify'])->map(fn ($key) => ['platform' => $key, 'when' => ['min' => ['members' => 2]], 'reason' => ':company has :members people.'])->all()]);
        $this->assertCount(3, $this->suggested($team));

        // A platform with a price can be suggested, but the suggestion never shows or implies an amount.
        PlatformPlan::create(['platform_key' => 'hr', 'slug' => 'team', 'name' => 'Team', 'model' => 'subscription', 'interval' => 'month', 'amount_minor' => 9900, 'currency' => 'ZAR', 'approved_at' => now(), 'active' => true]);
        $this->assertStringNotContainsString('99', $this->suggested($team)['hr']);

        config(['platform_recommendations.rules' => [], 'platform_recommendations.limit' => 0]);
        $html = $this->actingAs($team->owner)->get('/platforms')->assertOk()->getContent();
        $this->assertStringNotContainsString('Suggested for', $html);
        $this->assertGreaterThan(10, substr_count($html, '<li class="ix-catrow"'));
    }
}
