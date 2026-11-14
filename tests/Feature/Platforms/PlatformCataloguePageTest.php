<?php

namespace Tests\Feature\Platforms;

use App\Livewire\EcosystemWidget;
use App\Models\PlatformCatalogue;
use App\Models\PlatformEntitlement;
use App\Models\PlatformPlan;
use App\Models\Team;
use App\Models\User;
use App\Services\Platforms\CompanyCatalogue;
use App\Services\Platforms\PlatformAccess;
use App\Services\Platforms\PreloadedAccess;
use App\Support\PlatformCategories;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * The Platforms page and the page of one platform (R-50 section 7): every state a business can be shown, in words;
 * one action per platform that fits the reader; no price that an approved plan row does not carry; and nothing of
 * another company's.
 */
class PlatformCataloguePageTest extends PlatformTestCase
{
    /** The catalogue row of one platform, cut out of the page. */
    private function rowOf(string $html, string $key): string
    {
        $this->assertSame(1, preg_match('#<li class="ix-catrow" data-platform="'.$key.'".*?</li>#s', $html, $m), "no row for {$key}");

        return $m[0];
    }

    private function page(User $user): string
    {
        return $this->actingAs($user->fresh())->get('/platforms')->assertOk()->getContent();
    }

    private function plan(string $key, array $attributes = []): PlatformPlan
    {
        return PlatformPlan::create(array_merge([
            'platform_key' => $key, 'slug' => 'team', 'name' => 'Team', 'model' => 'subscription', 'interval' => 'month',
            'amount_minor' => 25000, 'currency' => 'ZAR', 'approved_at' => now(), 'active' => true,
        ], $attributes));
    }

    private function hold(Team $team, string $key, array $attributes): PlatformEntitlement
    {
        return PlatformEntitlement::create(array_merge(['team_id' => $team->id, 'platform_key' => $key, 'source' => 'paid', 'access_mode' => 'everyone', 'started_at' => now()], $attributes));
    }

    public function test_every_state_a_business_can_be_shown_appears_in_words_with_the_action_that_fits_the_owner(): void
    {
        $team = $this->company();
        $owner = $team->owner;

        $this->activatePlatforms($team, 'forms');                                                         // active
        $this->hold($team, 'hr', ['status' => 'trial', 'source' => 'trial', 'trial_ends_at' => now()->addDays(9)]); // trial
        $this->hold($team, 'projects', ['status' => 'pending']);                                          // activation pending
        $this->plan('sheet', ['slug' => 'free', 'name' => 'Free', 'model' => 'free', 'amount_minor' => 0, 'interval' => null]); // free to switch on
        $this->plan('agents');                                                                            // available for purchase
        $this->plan('design', ['trial_days' => 14]);                                                      // trial available
        $this->hold($team, 'engage', ['status' => 'payment_required']);                                   // payment needed
        $this->hold($team, 'press', ['status' => 'expired']);                                             // subscription expired
        $this->hold($team, 'pulse', ['status' => 'suspended', 'source' => 'unpriced_free', 'suspended_at' => now()]); // suspended
        PlatformCatalogue::where('key', 'auction')->update(['operational_status' => 'maintenance']);      // temporarily unavailable
        PlatformCatalogue::where('key', 'emall')->update(['operational_status' => 'not_launched']);       // not launched yet

        $html = $this->page($owner);

        $expected = [
            'files' => ['included', 'Included', 'open'],
            'finance' => ['included', 'Included', 'open'],
            'forms' => ['active', 'Active', 'open'],
            'hr' => ['trial', 'Trial', 'open'],
            'projects' => ['activation_pending', 'Activation pending', 'details'],
            'sheet' => ['free_to_activate', 'Free to switch on', 'switch_on'],
            'notify' => ['free_while_pricing_finalised', 'Free while pricing is being finalised', 'switch_on'],
            'agents' => ['available_for_purchase', 'Available for purchase', 'details'],
            'design' => ['trial_available', 'Trial available', 'start_trial'],
            'engage' => ['payment_required', 'Payment needed', 'finance'],
            'press' => ['subscription_expired', 'Subscription expired', 'finance'],
            'pulse' => ['suspended', 'Suspended', 'details'],
            'auction' => ['temporarily_unavailable', 'Temporarily unavailable', 'details'],
            'emall' => ['not_launched', 'Not launched yet', 'details'],
            'mines' => ['managed_in_platform', 'Managed in the platform', 'open'],
        ];

        foreach ($expected as $key => [$state, $label, $action]) {
            $row = $this->rowOf($html, $key);

            $this->assertStringContainsString('data-state="'.$state.'"', $row, $key);
            $this->assertStringContainsString($label, $row, $key);
            $this->assertSame(1, substr_count($row, 'data-platform-action='), "{$key} offers exactly one action");
            $this->assertStringContainsString('data-platform-action="'.$action.'"', $row, $key);
            $this->assertSame($state, $this->access()->businessState($team, $key), "the page shows the state the access service gives for {$key}");

            // The page of that platform says the same, with the reason.
            $detail = $this->actingAs($owner->fresh())->get('/platforms/'.$key)->assertOk()->assertSee($label)->getContent();
            $this->assertStringContainsString('data-state="'.$state.'"', $detail, $key);
        }
    }

    public function test_a_personal_workspace_sees_the_core_as_its_own_and_everything_else_as_for_companies(): void
    {
        $person = $this->person();

        $html = $this->page($person);

        $this->assertStringContainsString('In your personal workspace', $html);
        $this->assertStringContainsString('Create a company', $html);
        $this->assertStringContainsString('data-platform-action="open"', $this->rowOf($html, 'files'));

        $forms = $this->rowOf($html, 'forms');
        $this->assertStringContainsString('data-state="company_required"', $forms);
        $this->assertStringContainsString('Company required', $forms);
        $this->assertStringContainsString('data-platform-action="details"', $forms);
        $this->assertStringNotContainsString('Switch on', $html, 'a personal workspace is never offered a switch');

        $this->actingAs($person)->get('/platforms/forms')->assertOk()
            ->assertSee('Dot.Forms is for companies')
            ->assertSee('data-platform-action="create_company"', false);
    }

    public function test_an_ordinary_member_is_told_whom_to_ask_and_is_offered_no_switch(): void
    {
        $team = $this->company('Northwind Freight');
        $team->owner->forceFill(['name' => 'Lindiwe Dube'])->save();
        $member = $this->memberOf($team);
        $this->activatePlatforms($team, 'forms');

        $html = $this->page($member);

        $this->assertStringContainsString('data-platform-action="open"', $this->rowOf($html, 'forms'), 'a member opens what the company holds');
        $this->assertStringContainsString('data-platform-action="details"', $this->rowOf($html, 'hr'));
        $this->assertStringNotContainsString('data-platform-action="switch_on"', $html);

        $detail = $this->actingAs($member)->get('/platforms/hr')->assertOk()->getContent();
        $this->assertStringContainsString('Ask Lindiwe Dube, who owns Northwind Freight, to switch it on', $detail);
        $this->assertStringNotContainsString('data-platform-action="switch_on"', $detail);
        $this->assertStringNotContainsString('data-platform-history', $detail, 'the history is for the owner and administrators');
    }

    public function test_a_member_who_was_not_selected_sees_that_and_cannot_open_it(): void
    {
        $team = $this->company();
        $member = $this->memberOf($team);
        $chosen = $this->memberOf($team);
        $this->activatePlatforms($team, 'forms');
        $this->entitlements()->setAccessMode($team, 'forms', 'selected', $team->owner);
        $this->entitlements()->grantUser($team, 'forms', $chosen, $team->owner);

        $row = $this->rowOf($this->page($member), 'forms');
        $this->assertStringContainsString('Active for selected people', $row);
        $this->assertStringContainsString('data-platform-action="details"', $row);
        $this->actingAs($member)->get('/platforms/forms')->assertOk()->assertSee('you are not one of them yet')->assertSee('You are not one of them yet.');

        $this->assertStringContainsString('data-platform-action="open"', $this->rowOf($this->page($chosen), 'forms'));
        $this->actingAs($chosen)->get('/platforms/forms')->assertOk()->assertSee('You are one of them.');
    }

    public function test_the_companys_own_platforms_come_first_and_the_rest_sit_under_catalogue_categories(): void
    {
        $team = $this->company();
        $this->activatePlatforms($team, 'hr');

        $html = $this->page($team->owner);

        preg_match_all('#<h2 class="ix-reg__head" id="grp-([a-z-]+)">#', $html, $m);
        $this->assertSame('yours', $m[1][0], 'what the company holds is the first section');
        $this->assertStringContainsString(e("Your company's platforms"), $html);

        $known = array_map(fn (string $key) => str_replace('_', '-', $key), array_keys(PlatformCategories::ALL));
        $this->assertSame([], array_values(array_diff(array_slice($m[1], 1), $known)), 'every other section is a catalogue category');

        // Dot.HR was switched on, so it is listed once, with the company's own.
        $this->assertSame(1, substr_count($html, 'data-platform="hr"'));
        $yours = substr($html, strpos($html, 'id="reg-yours"'), strpos($html, 'id="reg-', strpos($html, 'id="reg-yours"') + 10) - strpos($html, 'id="reg-yours"'));
        foreach (['files', 'docs', 'tasks', 'finance', 'hr'] as $key) {
            $this->assertStringContainsString('data-platform="'.$key.'"', $yours, $key);
        }
        $this->assertStringNotContainsString('data-platform="forms"', $yours);

        // Every platform a business may be shown has exactly one row, and each row exactly one action.
        $shown = PlatformCatalogue::where('discoverable', true)->where('key', '!=', 'infodot')->pluck('key')->all();
        $this->assertSame(count($shown), substr_count($html, '<li class="ix-catrow"'));
        $this->assertSame(count($shown), substr_count($html, 'data-platform-action='));
    }

    public function test_every_category_has_a_plate_and_every_row_carries_the_original_logo(): void
    {
        $this->assertEqualsCanonicalizing(array_keys(PlatformCategories::ALL), array_keys(PlatformCategories::PLATES));

        $html = $this->page($this->company()->owner);

        foreach (PlatformCatalogue::where('discoverable', true)->where('key', '!=', 'infodot')->pluck('key') as $key) {
            $this->assertFileExists(resource_path('img/apps/'.$key.'.png'), "the original logo of {$key}");
            $this->assertMatchesRegularExpression('#ix-catrow__logo">\s*(<!--\[if BLOCK\]><!\[endif\]-->)?\s*<img#', $this->rowOf($html, $key), "{$key} shows its logo, not a drawn mark");
        }
    }

    public function test_no_price_is_shown_unless_an_approved_sellable_plan_row_exists(): void
    {
        $team = $this->company();
        // A draft, an unpriced plan and a switched-off plan: none of them is a price.
        $this->plan('forms', ['slug' => 'draft', 'amount_minor' => 99900, 'approved_at' => null]);
        $this->plan('forms', ['slug' => 'blank', 'amount_minor' => null, 'currency' => null]);
        $this->plan('forms', ['slug' => 'old', 'amount_minor' => 77700, 'active' => false]);
        // One approved price.
        $this->plan('agents', ['amount_minor' => 25000]);

        $html = $this->page($team->owner);

        foreach (['999', '777', 'R 9', 'R9'] as $figure) {
            $this->assertStringNotContainsString($figure, $this->rowOf($html, 'forms'));
        }
        $this->assertStringContainsString('Free while pricing is being finalised', $this->rowOf($html, 'forms'));
        $this->assertStringContainsString('From ZAR 250.00 a month', $this->rowOf($html, 'agents'));

        // Across the whole page the only amount is the approved one.
        $main = strip_tags(substr($html, (int) strpos($html, '<main'), (int) strpos($html, '</main>') - (int) strpos($html, '<main')));
        preg_match_all('/\b(?:ZAR|R)\s?\d[\d,.]*/', $main, $amounts);
        $this->assertSame(['ZAR 250.00'], array_values(array_unique($amounts[0])));

        $forms = $this->actingAs($team->owner)->get('/platforms/forms')->assertOk()->getContent();
        $this->assertStringContainsString('data-pricing="none"', $forms);
        $this->assertStringContainsString('No price has been set for Dot.Forms', $forms);
        // What a person reads on the page (tokens and component state in attributes are not text).
        $read = strip_tags(substr($forms, (int) strpos($forms, '<main'), (int) strpos($forms, '</main>') - (int) strpos($forms, '<main')));
        $this->assertStringNotContainsString('999', $read);
        $this->assertStringNotContainsString('ZAR', $read);

        $agents = $this->actingAs($team->owner)->get('/platforms/agents')->assertOk()->getContent();
        $this->assertStringContainsString('data-pricing="approved"', $agents);
        $this->assertStringContainsString('ZAR 250.00 a month', $agents);
        $this->assertStringContainsString('Paid activation is not open yet.', $agents);
        $this->assertStringContainsString('data-paid-not-open', $agents);
        $this->assertSame(0, preg_match('/<(a|button)[^>]*>\s*(Buy|Subscribe|Purchase|Pay|Start plan|Checkout)\b/i', $agents), 'no control implies a purchase can be completed');
        $this->assertSame(0, PlatformEntitlement::where('team_id', $team->id)->count(), 'reading the page asks for nothing');
        foreach (['PlatformCatalogueController.php' => app_path('Http/Controllers/PlatformCatalogueController.php'), 'EcosystemWidget.php' => app_path('Livewire/EcosystemWidget.php')] as $name => $file) {
            $this->assertStringNotContainsString('subscribe(', (string) file_get_contents($file), "{$name} never starts a paid plan");
        }
        $this->assertStringNotContainsString('data-platform-action="switch_on"', $agents, 'nothing here can start a paid plan');
    }

    public function test_search_finds_by_name_and_by_need_and_the_filters_narrow_by_category_and_state(): void
    {
        $team = $this->company();
        $this->activatePlatforms($team, 'hr');
        $keys = fn ($component) => $component->instance()->matches->keys()->all();

        $byName = Livewire::actingAs($team->owner)->test(EcosystemWidget::class)->set('q', 'dot.hr');
        $this->assertSame(['hr'], $keys($byName));

        $byNeed = Livewire::actingAs($team->owner)->test(EcosystemWidget::class)->set('q', 'leave');
        $this->assertContains('hr', $keys($byNeed), 'a need is found in what the platform does');
        $this->assertNotContains('auction', $keys($byNeed));
        $byNeed->assertSee('platforms match')->assertSee('Clear the search and filters');

        $category = Livewire::actingAs($team->owner)->test(EcosystemWidget::class)->set('category', 'human_resources');
        $this->assertSame(['hr'], $keys($category));

        $state = Livewire::actingAs($team->owner)->test(EcosystemWidget::class)->set('state', 'included');
        $this->assertEqualsCanonicalizing(['files', 'docs', 'tasks', 'finance'], $keys($state));

        $none = Livewire::actingAs($team->owner)->test(EcosystemWidget::class)->set('q', 'zzzz-nothing');
        $none->assertSee('No platform matches that')->call('clearFilters')->assertDontSee('No platform matches that');

        // The address carries the same filters, so a filtered page can be linked and works without scripts.
        $html = $this->actingAs($team->owner)->get('/platforms?category=human_resources&state=active')->assertOk()->getContent();
        $this->assertSame(1, substr_count($html, '<li class="ix-catrow"'));
        $this->assertStringContainsString('data-platform="hr"', $html);

        // A filter that names nothing real is ignored.
        $this->assertGreaterThan(10, substr_count($this->actingAs($team->owner)->get('/platforms?category=nope&state=nope')->assertOk()->getContent(), '<li class="ix-catrow"'));
    }

    public function test_the_page_of_a_platform_says_what_it_is_who_it_suits_and_who_may_open_it(): void
    {
        $team = $this->company('Northwind Freight');
        $entry = PlatformCatalogue::where('key', 'hr')->firstOrFail();

        $html = $this->actingAs($team->owner)->get('/platforms/hr')->assertOk()->getContent();

        foreach (['What it is for', 'Who it suits', 'What it does', 'What it costs', 'Who may open it', 'History'] as $heading) {
            $this->assertStringContainsString('>'.$heading.'</h2>', $html, $heading);
        }
        $this->assertStringContainsString(e($entry->description), $html);
        $this->assertStringContainsString(e($entry->audience), $html);
        $this->assertStringContainsString('For Northwind Freight', $html);
        $this->assertStringContainsString('Nobody yet.', $html);
        $this->assertStringContainsString('Northwind Freight has never switched Dot.HR on', $html);
        $this->assertSame(1, substr_count($html, 'data-platform-action='), 'one primary action');
        $this->assertSame(1, substr_count($html, '<h1'), 'one title');
    }

    public function test_a_platform_that_is_not_offered_to_businesses_has_no_page(): void
    {
        $owner = $this->company()->owner;
        PlatformCatalogue::where('key', 'pulse')->update(['discoverable' => false]);

        foreach (['infodot', 'memory', 'dopemine', 'brain', 'pulse', 'nothing-here'] as $key) {
            $this->actingAs($owner)->get('/platforms/'.$key)->assertNotFound();
        }

        $this->assertStringNotContainsString('data-platform="pulse"', $this->page($owner));
        $this->get('/platforms/forms')->assertOk();
    }

    public function test_the_pages_are_for_signed_in_verified_people(): void
    {
        $this->get('/platforms/forms')->assertRedirect('/login');

        $unverified = User::factory()->create(['email_verified_at' => null]);
        $this->actingAs($unverified)->get('/platforms/forms')->assertRedirect(route('verification.notice'));
        $this->actingAs($unverified)->post('/platforms/forms/switch-on')->assertRedirect(route('verification.notice'));
    }

    public function test_nothing_of_another_company_is_ever_shown(): void
    {
        $ours = $this->company('Northwind Freight');
        $theirs = $this->company('Kestrel Mining');
        $theirs->owner->forceFill(['name' => 'Zanele Other'])->save();
        $this->activatePlatforms($theirs, 'hr', 'forms');
        $this->entitlements()->setAccessMode($theirs, 'hr', 'selected', $theirs->owner);

        $html = $this->page($ours->owner);
        $this->assertStringContainsString('data-state="free_while_pricing_finalised"', $this->rowOf($html, 'hr'), 'their entitlement is not ours');

        $detail = $this->actingAs($ours->owner)->get('/platforms/hr')->assertOk()->getContent();
        foreach (['Kestrel Mining', 'Zanele Other', 'Switched on', 'Limited to selected people'] as $theirsOnly) {
            $this->assertStringNotContainsString($theirsOnly, $detail);
            $this->assertStringNotContainsString($theirsOnly, $html);
        }
        $this->assertStringContainsString('Northwind Freight has never switched Dot.HR on', $detail);
    }

    public function test_a_listing_reads_the_records_once_and_agrees_with_the_access_service(): void
    {
        $team = $this->company();
        $this->activatePlatforms($team, 'forms', 'hr');
        $this->plan('agents');
        $this->plan('sheet', ['slug' => 'free', 'model' => 'free', 'amount_minor' => 0]);

        $plain = app(PlatformAccess::class);
        $preloaded = new PreloadedAccess;

        foreach (PlatformCatalogue::pluck('key') as $key) {
            $this->assertSame($plain->offer($key), $preloaded->offer($key), "offer: {$key}");
            $this->assertSame($plain->businessState($team, $key), $preloaded->businessState($team, $key), "state: {$key}");
            $this->assertSame($plain->companyHolds($team, $key), $preloaded->companyHolds($team, $key), "holds: {$key}");
            $this->assertSame($plain->decideFor($team->owner, $team, $key)->reason, $preloaded->decideFor($team->owner, $team, $key)->reason, "decision: {$key}");
        }

        $owner = $team->owner->fresh();
        $owner->currentTeam;
        DB::flushQueryLog();
        DB::enableQueryLog();
        (new CompanyCatalogue)->rows($owner, $owner->currentTeam);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(12, $queries, "the catalogue took {$queries} queries; it must not be several per platform");
    }
}
