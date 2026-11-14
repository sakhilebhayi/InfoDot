<?php

namespace Tests\Feature\Platforms;

use App\Livewire\DotSwitcher;
use App\Models\PlatformCatalogue;
use App\Models\User;
use App\Support\PlatformCategories;
use Livewire\Livewire;

/**
 * Where platforms are listed outside the Platforms page (R-50 section 12, step 2): Dot Apps in the top bar shows only
 * what the current company holds and this person may open, and InfoDot's own Analytics page shows the company's own
 * platforms instead of the state of the whole fleet.
 */
class PlatformNavigationTest extends PlatformTestCase
{
    /** @return list<string> the platform keys Dot Apps offers this person */
    private function bays(User $user): array
    {
        return collect(Livewire::actingAs($user->fresh())->test(DotSwitcher::class)->instance()->groups)->flatMap(fn (array $group) => array_keys($group['members']))->values()->all();
    }

    public function test_dot_apps_shows_only_what_the_company_holds_with_a_link_to_the_rest(): void
    {
        $team = $this->company('Northwind Freight');

        $this->assertEqualsCanonicalizing(['files', 'docs', 'tasks', 'finance'], $this->bays($team->owner), 'a new company has the free core');

        $this->activatePlatforms($team, 'hr');
        $this->assertEqualsCanonicalizing(['files', 'docs', 'tasks', 'finance', 'hr'], $this->bays($team->owner));

        $html = $this->actingAs($team->owner->fresh())->get('/home')->assertOk()->getContent();
        $map = substr($html, strpos($html, 'id="ix-yardmap"'));
        $map = substr($map, 0, strpos($map, '</nav>') ?: strlen($map));

        $this->assertSame(5, substr_count($map, 'class="ix-bay"'));
        $this->assertStringContainsString('The 5 platforms Northwind Freight has and you can open', $map);
        $this->assertStringContainsString('data-all-platforms', $map);
        $this->assertStringContainsString('href="'.route('ecosystem.widget').'"', $map);
        $this->assertStringContainsString('Dot.HR', $map);
        foreach (['Dot.Forms', 'Dot.Mines', 'Dot.Charts', 'Dot.Agents', 'is-soon', 'Soon', 'not live yet'] as $absent) {
            $this->assertStringNotContainsString($absent, $map, $absent);
        }
    }

    public function test_dot_apps_leaves_out_what_this_person_may_not_open(): void
    {
        $team = $this->company();
        $member = $this->memberOf($team);
        $this->activatePlatforms($team, 'hr', 'forms');
        $this->entitlements()->setAccessMode($team, 'hr', 'selected', $team->owner);

        $this->assertContains('hr', $this->bays($team->owner));
        $this->assertNotContains('hr', $this->bays($member), 'selected people only, and this member is not one');
        $this->assertContains('forms', $this->bays($member));

        // A platform that is down is not offered for opening; one that was switched off is gone.
        PlatformCatalogue::where('key', 'forms')->update(['operational_status' => 'maintenance']);
        $this->assertNotContains('forms', $this->bays($member));
        PlatformCatalogue::where('key', 'forms')->update(['operational_status' => 'operational']);
        $this->entitlements()->deactivate($team, 'forms', $team->owner);
        $this->assertNotContains('forms', $this->bays($team->owner));

        // A platform switched off in the registry cannot be opened, so it is not a bay either.
        config(['ecosystem.platforms.tasks.active' => false]);
        $this->assertNotContains('tasks', $this->bays($team->owner));
    }

    public function test_dot_apps_in_a_personal_workspace_is_the_free_core(): void
    {
        $person = $this->person();

        $this->assertEqualsCanonicalizing(['files', 'docs', 'tasks', 'finance'], $this->bays($person));
        $this->actingAs($person)->get('/home')->assertOk()->assertSee('The 4 platforms in your personal workspace');
    }

    public function test_dot_apps_groups_come_from_the_catalogue(): void
    {
        $team = $this->company();
        $this->activatePlatforms($team, 'hr', 'design');

        $groups = Livewire::actingAs($team->owner->fresh())->test(DotSwitcher::class)->instance()->groups;

        foreach ($groups as $group) {
            $key = array_search($group['name'], PlatformCategories::ALL, true);
            $this->assertNotFalse($key, $group['name'].' is a catalogue category');
            $this->assertSame(PlatformCategories::PLATES[$key], [$group['code'], $group['tone']]);

            foreach ($group['members'] as $platform => $row) {
                $this->assertSame($key, PlatformCategories::primary(PlatformCatalogue::where('key', $platform)->value('categories')), "{$platform} sits under the first category its catalogue row names");
            }
        }

        // Moving a platform in the catalogue moves its bay: nothing about the grouping is written in the page.
        PlatformCatalogue::where('key', 'hr')->update(['categories' => json_encode(['learning_development'])]);
        $names = collect(Livewire::actingAs($team->owner->fresh())->test(DotSwitcher::class)->instance()->groups)->mapWithKeys(fn (array $group) => [$group['name'] => array_keys($group['members'])]);
        $this->assertSame(['hr'], $names['Learning and Development']);

        foreach (['livewire/dot-switcher.blade.php', 'livewire/ecosystem-widget.blade.php'] as $view) {
            $source = (string) file_get_contents(resource_path('views/'.$view));
            $this->assertDoesNotMatchRegularExpression("/\[\s*'(files|docs|forms|hr|agents)'\s*,/", $source, "{$view} lists no platform keys of its own");
        }
    }

    public function test_analytics_shows_the_companys_own_platforms_not_the_fleet(): void
    {
        $team = $this->company('Northwind Freight');
        $member = $this->memberOf($team);
        $catalogue = PlatformCatalogue::where('discoverable', true)->where('key', '!=', 'infodot')->count();

        $panel = function (User $user): string {
            $html = $this->actingAs($user->fresh())->get('/analytics')->assertOk()->getContent();
            $this->assertSame(1, preg_match('#<section class="ix-panel" aria-labelledby="ixa-platforms" data-company-platforms>.*?</section>#s', $html, $m));

            return $m[0];
        };

        $new = $panel($team->owner);
        $this->assertStringContainsString(e("Your company's platforms"), $new);
        $this->assertStringContainsString("4 of the {$catalogue} in the catalogue", $new);
        foreach (['Dot.Files', 'Dot.docs', 'Dot.Tasks', 'Dot.Finance', 'Included'] as $shown) {
            $this->assertStringContainsString($shown, $new);
        }
        foreach (['Dot.Forms', 'Dot.HR', 'registered', 'Live', 'live /', 'Not live yet', 'Platform coverage'] as $absent) {
            $this->assertStringNotContainsString($absent, $new, $absent);
        }

        $this->activatePlatforms($team, 'hr');
        $after = $panel($member);
        $this->assertStringContainsString("5 of the {$catalogue} in the catalogue", $after);
        $this->assertStringContainsString('Dot.HR', $after);
        $this->assertStringContainsString('Active', $after);
        $this->assertStringContainsString('href="'.route('platforms.show', ['platform' => 'hr']).'"', $after);

        // Another company still has only its own.
        $other = $this->company('Kestrel Mining');
        $theirs = $panel($other->owner);
        $this->assertStringContainsString("4 of the {$catalogue} in the catalogue", $theirs);
        $this->assertStringNotContainsString('Dot.HR', $theirs);

        // A personal workspace: the free core, called what it is.
        $alone = $panel($this->person());
        $this->assertStringContainsString('Your platforms', $alone);
        $this->assertStringContainsString("4 of the {$catalogue} in the catalogue", $alone);

        // Whether a platform's server answers is not a business's concern: switching one off in the registry changes no count here.
        config(['ecosystem.platforms.ehail.active' => false]);
        $this->assertStringContainsString("5 of the {$catalogue} in the catalogue", $panel($team->owner));
        $this->assertStringNotContainsString('registered', $this->actingAs($team->owner)->get('/analytics')->getContent());
    }

    public function test_analytics_claims_no_intelligence_engines_because_none_is_recorded_for_the_company(): void
    {
        // The page used to list fifteen "engines", four of them "Active", from an array in the view. Nothing recorded about a company
        // backs any of them, so the panel is gone and no engine is named or counted as active.
        $team = $this->company('Northwind Freight');

        foreach ([$team->owner, $this->memberOf($team), $this->person()] as $person) {
            $html = $this->actingAs($person->fresh())->get('/analytics')->assertOk()->getContent();

            foreach (['Intelligence engines', 'ixa-engines', 'Business Intelligence', 'Community Intelligence', 'People Intelligence', 'Predictive Intelligence', 'Planned'] as $absent) {
                $this->assertStringNotContainsString($absent, $html, $absent);
            }
            $this->assertDoesNotMatchRegularExpression('/\d+ active \/ \d+/', strip_tags($html));
        }

        $this->assertStringNotContainsString("'status' => 'active'", (string) file_get_contents(resource_path('views/analytics/dashboard.blade.php')));
    }
}
