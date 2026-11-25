<?php

namespace Tests\Feature\Onboarding;

use App\Models\Team;
use App\Services\Onboarding\FirstFourPlatforms;
use App\Services\Platforms\CompanyCatalogue;
use Tests\Feature\Platforms\PlatformTestCase;

/** R-62 phase 5: the first four platforms a company is offered are free, on, not yet held, and ordered by what it said about itself. */
class FirstFourPlatformsTest extends PlatformTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // The local environment may point platforms at development hosts; the offer must not depend on that.
        foreach (array_keys(config('ecosystem.platforms')) as $key) {
            config(['ecosystem.platforms.'.$key.'.url' => 'https://'.$key.'.infodot.co.za']);
        }
    }

    private function offered(Team $team): array
    {
        return app(FirstFourPlatforms::class)->for($team->owner->fresh(), $team->fresh())->pluck('key')->all();
    }

    public function test_at_most_four_are_offered_and_every_one_is_free_switchable_and_not_held(): void
    {
        $team = $this->company();
        $picks = app(FirstFourPlatforms::class)->for($team->owner, $team);

        $this->assertLessThanOrEqual(FirstFourPlatforms::LIMIT, $picks->count());
        $this->assertGreaterThan(0, $picks->count());
        foreach ($picks as $pick) {
            $this->assertContains($pick['row']['state'], CompanyCatalogue::SWITCHABLE, $pick['key']);
            $this->assertFalse($pick['row']['held'], $pick['key']);
            $this->assertNotEmpty($pick['reasons'], 'nothing is offered without a reason');
        }
    }

    public function test_a_platform_the_company_switches_on_is_replaced_by_the_next_one(): void
    {
        $team = $this->company();
        $before = $this->offered($team);
        $this->assertCount(FirstFourPlatforms::LIMIT, $before);

        $this->activatePlatforms($team, $before[0]);
        $after = $this->offered($team);

        $this->assertNotContains($before[0], $after);
        $this->assertSame(array_slice($before, 1), array_slice($after, 0, 3), 'the order of the others does not change');
        $this->assertCount(FirstFourPlatforms::LIMIT, $after);
    }

    public function test_the_aims_a_company_chose_come_first(): void
    {
        $team = $this->company();
        $default = $this->offered($team);
        $target = collect(config('onboarding.objectives'))->map(fn ($o) => $o['platforms'][0] ?? null)->filter()
            ->first(fn ($key) => ! in_array($key, $default, true) && $this->canBeOffered($team, $key));
        $this->assertNotNull($target, 'a platform an aim points to that is not already first');
        $objective = collect(config('onboarding.objectives'))->search(fn ($o) => ($o['platforms'][0] ?? null) === $target);

        $team->profileOrNew()->forceFill(['objectives' => [$objective]])->save();

        $this->assertSame($target, $this->offered($team)[0]);
    }

    private function canBeOffered(Team $team, string $key): bool
    {
        $row = app(CompanyCatalogue::class)->rows($team->owner, $team)->get($key);

        return $row !== null && ! $row['held'] && in_array($row['state'], CompanyCatalogue::SWITCHABLE, true);
    }

    public function test_a_personal_workspace_is_offered_nothing(): void
    {
        $person = $this->person();

        $this->assertSame([], app(FirstFourPlatforms::class)->for($person, $person->currentTeam)->all());
    }

    public function test_the_page_lists_them_for_the_owner_and_they_can_be_switched_on(): void
    {
        $team = $this->company();
        $offered = $this->offered($team);

        $html = $this->actingAs($team->owner)->get(route('onboarding.recommendations'))->assertOk()->getContent();
        $this->assertStringContainsString('data-first-four', $html);
        foreach ($offered as $key) {
            $this->assertStringContainsString('value="'.$key.'" checked', $html);
        }

        $this->actingAs($team->owner)->post(route('onboarding.platforms.activate'), ['company' => $team->uuid, 'platforms' => [$offered[0], $offered[1]]])
            ->assertRedirect(route('onboarding.recommendations'))->assertSessionHas('status');

        $held = app(CompanyCatalogue::class)->rows($team->owner->fresh(), $team->fresh())->filter(fn ($r) => $r['held'])->keys()->all();
        $this->assertContains($offered[0], $held);
        $this->assertContains($offered[1], $held);
        $this->assertNotContains($offered[2], $held);
    }

    public function test_only_what_is_on_the_list_can_be_switched_on_this_way(): void
    {
        $team = $this->company();
        $offered = $this->offered($team);
        $notOffered = collect(array_keys(config('ecosystem.platforms')))->first(fn ($k) => ! in_array($k, $offered, true) && $this->access()->governs($k));

        $this->actingAs($team->owner)->post(route('onboarding.platforms.activate'), ['company' => $team->uuid, 'platforms' => [$notOffered, 'made-up']])
            ->assertSessionHas('status', fn ($s) => str_contains($s, 'Nothing was switched on.'));

        $held = app(CompanyCatalogue::class)->rows($team->owner->fresh(), $team->fresh())->filter(fn ($r) => $r['held'])->keys()->all();
        $this->assertNotContains($notOffered, $held);
    }

    public function test_a_plain_member_cannot_switch_anything_on_and_a_wrong_company_is_refused(): void
    {
        $team = $this->company();
        $member = $this->memberOf($team);
        $offered = $this->offered($team);

        $this->actingAs($member)->post(route('onboarding.platforms.activate'), ['company' => $team->uuid, 'platforms' => [$offered[0]]])->assertStatus(404);

        $other = $this->company('Kestrel Mining');
        $this->actingAs($team->owner)->post(route('onboarding.platforms.activate'), ['company' => $other->uuid, 'platforms' => [$offered[0]]])->assertStatus(409);
        $this->assertNotContains($offered[0], app(CompanyCatalogue::class)->rows($team->owner->fresh(), $team->fresh())->filter(fn ($r) => $r['held'])->keys()->all());
    }

    public function test_more_than_four_in_one_request_is_refused(): void
    {
        $team = $this->company();

        $this->actingAs($team->owner)->post(route('onboarding.platforms.activate'), ['company' => $team->uuid, 'platforms' => ['a', 'b', 'c', 'd', 'e']])
            ->assertSessionHasErrors('platforms');
    }

    public function test_a_guest_cannot_use_it(): void
    {
        $this->post(route('onboarding.platforms.activate'), [])->assertRedirect();
    }
}
