<?php

namespace Tests\Feature\Stabilisation;

use App\Models\Team;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R-49: defects the real-browser journeys (tests/Browser) exposed, held here so they stay fixed without a browser.
 */
class BrowserJourneyFixesTest extends TestCase
{
    use RefreshDatabase;

    private function person(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $team = Team::factory()->create(['user_id' => $user->id, 'name' => $user->name."'s Team", 'personal_team' => true]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user;
    }

    /** The controller flashed "Your question was posted." and the list it redirects to never showed it. */
    public function test_the_question_list_says_the_question_was_posted(): void
    {
        $user = $this->person();

        $this->actingAs($user)->followingRedirects()->post('/questions/add', [
            'topics' => [Topic::query()->value('id')],
            'question' => 'How do we onboard a new supplier?',
            'description' => 'We keep losing the paperwork between purchasing and accounts.',
        ])->assertOk()->assertSee('<p class="ix-alert" role="status">Your question was posted.</p>', false);

        // Nothing is said on an ordinary visit.
        $this->actingAs($user)->get('/questions')->assertOk()->assertDontSee('Your question was posted.');
    }

    /** Publishing a solution went back to the list with no word that anything had been saved. */
    public function test_the_solution_list_says_the_solution_was_saved(): void
    {
        $user = $this->person();

        $this->actingAs($user)->followingRedirects()->post('/solution/add', [
            'topics' => [Topic::query()->value('id')],
            'solution_title' => 'How to register a business online',
            'solution_description' => 'A practical sequence to complete your registration.',
            'tags-input' => 'business registration, legal',
            'duration' => 7,
            'duration_type' => 'days',
            'steps' => 1,
            'solution_heading' => ['Create account'],
            'solution_body' => ['Create an account on the portal.'],
        ])->assertOk()->assertSee('<p class="ix-alert" role="status">Your solution was saved.</p>', false);

        $this->assertDatabaseHas('solutions', ['solution_title' => 'How to register a business online']);
        $this->actingAs($user)->get('/solutions')->assertOk()->assertDontSee('Your solution was saved.');
    }

    /**
     * Analytics drew every contributor's face from gravatar.com, which the site's own content security policy
     * refuses: three broken pictures and six console errors on the page. It uses the person's own photo or initial now.
     */
    public function test_analytics_draws_contributors_without_a_third_party_picture(): void
    {
        $user = $this->person();
        $user->forceFill(['name' => 'Zodwa Khumalo'])->save();

        $html = $this->actingAs($user)->get('/analytics')->assertOk()->getContent();

        $this->assertStringNotContainsString('gravatar.com', $html);
        $this->assertMatchesRegularExpression('/<span class="ix-avatar">\s*Z\s*<\/span>/', $html);
    }

    /**
     * The industry field on team settings carried a Blade expression in an Alpine binding (:disabled="! Gate::check(...)"),
     * which the browser tried to run as JavaScript: an uncaught error on every visit, and the field was never disabled
     * for a member who may not change it.
     */
    public function test_team_settings_disables_the_industry_field_in_html_not_in_script(): void
    {
        $owner = $this->person();
        $company = Team::factory()->create(['user_id' => $owner->id, 'personal_team' => false]);
        $owner->forceFill(['current_team_id' => $company->id])->save();
        $member = $this->person();
        $company->users()->attach($member, ['role' => 'editor']);
        $member->forceFill(['current_team_id' => $company->id])->save();

        $forOwner = $this->actingAs($owner)->get('/teams/'.$company->id)->assertOk()->getContent();
        $this->assertStringNotContainsString('Gate::check', $forOwner);
        $this->assertDoesNotMatchRegularExpression('/<select id="industry"[^>]*\sdisabled/', $forOwner);

        $forMember = $this->actingAs($member)->get('/teams/'.$company->id)->assertOk()->getContent();
        $this->assertStringNotContainsString('Gate::check', $forMember);
        $this->assertMatchesRegularExpression('/<select id="industry"[^>]*\sdisabled/', $forMember);
    }

    /**
     * R-52: the swipe-hidden tab strip is gone. The shared header lists every place in a real drawer on a phone and keeps the
     * current one open and marked, so no script has to scroll a strip to the page in view.
     */
    public function test_the_tab_row_is_a_drawer_with_the_current_page_marked(): void
    {
        $script = file_get_contents(resource_path('js/app.js'));
        $this->assertStringNotContainsString('.ix-tabs', $script);

        $html = $this->actingAs($this->person())->get('/questions')->assertOk()->getContent();
        $this->assertStringContainsString('data-dh-menu-button', $html);
        $this->assertMatchesRegularExpression('#<div class="dh-drawer" id="dh-drawer".*aria-current="page"#s', $html);
    }

    /**
     * Every Jetstream dialog (delete account, log out other browsers, confirm password, delete team) bound its "show" with
     * the Livewire 2 form `@entangle(...).defer`. Livewire 3 has no `.defer` there, so "show" was undefined and pressing the
     * button did nothing at all.
     */
    public function test_account_dialogs_are_bound_the_livewire_3_way(): void
    {
        $html = $this->actingAs($this->person())->get('/user/profile/edit')->assertOk()->getContent();

        $this->assertStringContainsString("entangle('confirmingUserDeletion')", $html);
        $this->assertStringContainsString("entangle('confirmingLogout')", $html);
        $this->assertDoesNotMatchRegularExpression('/entangle\([^)]*\)\.defer/', $html);
        // Closing a dialog hands focus back to the button that opened it.
        $this->assertStringContainsString('opener.focus()', $html);
    }

    /** The menus in the top bar closed on Escape and left keyboard focus on a link that was no longer on screen. */
    public function test_top_bar_menus_give_focus_back_on_escape(): void
    {
        $html = $this->actingAs($this->person())->get('/home')->assertOk()->getContent();

        // R-52: the header's menus (nav groups, More, the account menu, the drawer, the search sheet) are the shared dot-ui header, whose script
        // closes on Escape and hands focus back to the opener (its own browser test). The one menu still written here is Dot Apps.
        $this->assertSame(1, substr_count($html, '@keydown.escape="if (open) { open = false; $el.querySelector(\'button\').focus() }"'));
        $this->assertStringContainsString('/dot-ui/js/dot-ui-core.min.js', $html);
        $this->assertStringContainsString('data-dh-menu-trigger', $html);
    }

    /** Names a screen reader hears must contain the words on screen (WCAG 2.5.3), and every file field needs a name. */
    public function test_accessible_names_found_by_the_scan(): void
    {
        $owner = $this->person();
        $company = Team::factory()->create(['user_id' => $owner->id, 'name' => 'Kestrel Drilling', 'personal_team' => false]);
        $owner->forceFill(['current_team_id' => $company->id])->save();

        $home = $this->actingAs($owner)->get('/home')->assertOk()->getContent();
        // R-52: the company is named in the account menu button's accessible name's menu and in the drawer, and the company menu popover is gone.
        $this->assertMatchesRegularExpression('/<span class="dh-menu__sub" title="Kestrel Drilling · Company">Kestrel Drilling · Company<\/span>/', $home);
        $this->assertStringNotContainsString('Company menu: ', $home);
        $this->assertMatchesRegularExpression('/aria-label="Account menu: [^"]+"/', $home, 'the account button is named for the person');
        // The quick links sit inside the suggestions list box, so they are options like everything else in it.
        $this->assertSame(5, substr_count($home, 'class="dsr-chip" role="option"'));

        $edit = $this->actingAs($owner)->get(route('business.edit', $company))->assertOk()->getContent();
        $this->assertStringContainsString('data-crop-file aria-label="Logo picture"', $edit);
        $this->assertStringContainsString('data-crop-file aria-label="Cover photograph"', $edit);
    }
}
