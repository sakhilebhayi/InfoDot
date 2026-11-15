<?php

namespace Tests\Feature;

use App\Livewire\Search;
use App\Models\Questions;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * R-52 on InfoDot, the reference platform: Roboto from the shared dot-ui package, the shared header (56px desktop, one 52px
 * row and a real drawer on a phone), and InfoDot's own search drawn by the shared pill without changing how it behaves.
 * The things that cannot be asserted from markup (the 56px, the centring, the 44px targets) are measured by the responsive
 * harness, whose results are in the pull request.
 */
class R52ShellTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Cache::flush();
        config(['services.dopemine.secret' => null]);
    }

    private function member(string $company = 'Acme Ltd'): User
    {
        $user = User::factory()->create(['name' => 'Alice Acme', 'email_verified_at' => Carbon::now()]);
        $team = Team::forceCreate(['user_id' => $user->id, 'name' => $company, 'personal_team' => false]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        return $user->fresh();
    }

    // ---- Roboto and the head --------------------------------------------------------------------------------------

    public function test_every_layout_is_roboto_from_this_site_and_links_the_minified_dot_ui_first(): void
    {
        $user = $this->member();

        foreach ([[null, '/'], [null, '/faqs'], [null, '/login'], [null, '/register'], [null, '/businesses'], [$user, '/home'], [$user, '/questions'], [null, '/no-such-page-r52']] as [$viewer, $path]) {
            $response = $viewer ? $this->actingAs($viewer)->get($path) : $this->get($path);
            $html = $response->getContent();

            $this->assertMatchesRegularExpression('#<html[^>]*class="dot-roboto"#', $html, $path);
            $this->assertStringContainsString('/dot-ui/css/dot-ui.min.css', $html, $path);
            $this->assertStringContainsString('/dot-ui/js/dot-ui-core.min.js', $html, $path);
            // The shared stylesheet is the FIRST stylesheet, ahead of the product's own.
            $first = strpos($html, '<link rel="stylesheet"');
            $this->assertSame($first, strpos($html, '<link rel="stylesheet" href="'.url('/dot-ui/css/dot-ui.min.css').'">'), $path.': dot-ui is not the first stylesheet');
            $this->assertStringNotContainsString('fonts.googleapis', $html, $path);
            $this->assertStringNotContainsString('fonts.gstatic', $html, $path);
            $this->assertStringNotContainsString('Archivo', $html, $path);
            $this->assertStringNotContainsString('Big Shoulders', $html, $path);
        }
    }

    public function test_the_dot_ui_files_the_pages_link_exist_and_are_served_from_the_site(): void
    {
        foreach (['css/dot-ui.min.css', 'js/dot-ui-core.min.js', 'js/dot-lazy.min.js', 'fonts/roboto/roboto-latin-wght-normal.woff2', 'fonts/roboto/roboto-latin-ext-wght-normal.woff2'] as $file) {
            $this->assertFileExists(public_path('dot-ui/'.$file), $file);
            $this->assertGreaterThan(0, filesize(public_path('dot-ui/'.$file)), $file);
        }
        $css = (string) file_get_contents(public_path('dot-ui/css/dot-ui.min.css'));
        $this->assertSame(0, preg_match('#url\(\s*["\']?(https?:)?//#', $css), 'the shared stylesheet names another host');
        $this->assertStringContainsString('font-family:Roboto', str_replace(["\n", ' '], '', $css));
    }

    public function test_the_shared_files_add_no_inline_script_or_style_to_the_header(): void
    {
        $html = $this->actingAs($this->member())->get('/questions')->assertOk()->getContent();
        preg_match('#<header class="dh.*?</header>#s', $html, $header);
        $this->assertNotEmpty($header);
        $this->assertStringNotContainsString('<script', $header[0]);
        $this->assertStringNotContainsString('<style', $header[0]);
        // The shared parts (brand, nav, search pill, account menu, drawer) carry no inline style. InfoDot's own Dot Apps switcher, a Livewire
        // component, sets its category colour with a style attribute that the site's CSP allows; it is not part of the shared markup.

        preg_match('#<div class="dh-start">.*?</nav>#s', $html, $start);
        $this->assertDoesNotMatchRegularExpression('#\sstyle=#', $start[0] ?? '', 'the brand and nav carry no inline style');
        preg_match('#<div class="dh-drawer".*?</section>#s', $html, $drawer);
        $this->assertDoesNotMatchRegularExpression('#\sstyle=#', $drawer[0] ?? '', 'the drawer carries no inline style');
        $this->assertDoesNotMatchRegularExpression('#class="dh-search".*?\sstyle=#s', substr($html, (int) strpos($html, 'class="dh-search"'), 3000), 'the search pill carries no inline style');

        // The two dot-ui tags in <head> are external files, and the script carries the page's CSP nonce.
        $this->assertMatchesRegularExpression('#<script src="[^"]*/dot-ui/js/dot-ui-core\.min\.js" defer nonce="[^"]*"></script>#', $html);
    }

    // ---- the header ------------------------------------------------------------------------------------------------

    public function test_the_signed_in_bar_is_the_shared_header_with_a_drawer_and_no_second_row_of_tabs(): void
    {
        $user = $this->member();
        $html = $this->actingAs($user)->get('/home')->assertOk()->getContent();

        $this->assertStringContainsString('<header class="dh ix-header"', $html);
        $this->assertStringContainsString('data-dh-menu-button', $html, 'a menu button for a phone');
        $this->assertStringContainsString('data-dh-search-toggle', $html, 'a search button for a phone');
        $this->assertStringContainsString('id="dh-drawer"', $html, 'a real drawer');
        $this->assertStringNotContainsString('class="ix-top', $html, 'the old four-row bar is gone');
        $this->assertStringNotContainsString('class="ix-tabs"', $html);
        $this->assertStringNotContainsString('ix-owner', $html, 'the company switch moved into the account menu');

        // Notifications are the bell's alone (they were in the bell and the avatar menu); Inbox sits in the account menu.
        $this->assertSame(1, preg_match_all('#class="dh-icon-btn dh-bell[^"]*"#', $html));
        preg_match('#<div id="dh-account-menu".*?</form>\s*</div>\s*</div>#s', $html, $menu);
        $this->assertStringNotContainsString('Notifications</', $menu[0] ?? '', 'no second Notifications entry in the account menu');
        $this->assertStringContainsString('Inbox', $menu[0] ?? '');
        $this->assertStringContainsString('Company settings', $menu[0] ?? '');
        $this->assertStringContainsString('Log out', $menu[0] ?? '');

        // The bar keeps six or fewer top-level places: Home and three groups (staff get a fourth group).
        preg_match('#<ul class="dh-nav__list">(.*?)</ul>\s*</nav>#s', $html, $nav);
        $this->assertLessThanOrEqual(7, substr_count($nav[1] ?? '', 'class="dh-nav__item'), 'at most seven top-level items in the bar');
    }

    public function test_the_switcher_and_the_company_switch_keep_working_in_the_new_bar(): void
    {
        $user = $this->member();
        $second = Team::forceCreate(['user_id' => $user->id, 'name' => 'Second Co', 'personal_team' => false]);
        $user->teams()->attach($second, ['role' => 'admin']);

        $html = $this->actingAs($user)->get('/home')->assertOk()->getContent();

        $this->assertStringContainsString('aria-label="Dot Apps"', $html, 'InfoDot keeps its own Dot Apps switcher');
        $this->assertStringContainsString('id="ix-yardmap"', $html);
        $this->assertStringContainsString('Switch company', $html);
        // The company switch is a PUT form per company, with the token, as Jetstream's own switchable-team form was.
        $this->assertSame(4, preg_match_all('#<form class="dh-menu__form" method="post" action="'.preg_quote(route('current-team.update'), '#').'">\s*<input type="hidden" name="_token"[^>]*>\s*<input type="hidden" name="_method" value="PUT">\s*<input type="hidden" name="team_id" value="\d+">#s', $html), 'one form per company, in the account menu and again in the drawer');

        $this->actingAs($user)->put(route('current-team.update'), ['team_id' => $second->id])->assertRedirect();
        $this->assertSame($second->id, $user->fresh()->current_team_id);
    }

    public function test_staff_get_an_admin_group_and_everyone_else_does_not(): void
    {
        $user = $this->member();
        $html = $this->actingAs($user)->get('/home')->assertOk()->getContent();
        $this->assertStringNotContainsString('Platform admin', $html);
        $this->assertStringNotContainsString('>Moderation<', $html);

        $user->forceFill(['is_moderator' => true])->save();
        $html = $this->actingAs($user->fresh())->get('/home')->assertOk()->getContent();
        $this->assertStringContainsString('>Admin<', $html);
        $this->assertStringContainsString('Moderation', $html);
    }

    public function test_a_visitor_sees_the_shared_header_signed_out_with_the_three_public_places(): void
    {
        $html = $this->get('/faqs')->assertOk()->getContent();

        $this->assertStringContainsString('ix-header--public', $html);
        $this->assertStringContainsString('Questions people ask', $html);
        $this->assertStringContainsString('Browse public answers', $html);
        $this->assertStringContainsString('Create account', $html);
        $this->assertStringContainsString('Sign in', $html);
        $this->assertStringContainsString('data-dh-menu-button', $html);

        // The way in never links to the page already open.
        $login = $this->get('/login')->assertOk()->getContent();
        $this->assertStringNotContainsString('>Sign in</a>', $login);
        $this->assertStringContainsString('Create account', $login);
    }

    public function test_error_pages_keep_the_shared_header_without_the_build(): void
    {
        $html = $this->get('/no-such-page-r52')->assertNotFound()->getContent();

        $this->assertMatchesRegularExpression('#<header class="dh[^"]*ix-header#', $html);
        $this->assertStringContainsString('/dot-ui/css/dot-ui.min.css', $html, 'the header and Roboto are static files, not part of the build');
    }

    // ---- the search: on every page type, one implementation, tenant-scoped ----------------------------------------

    public function test_the_header_search_is_on_every_signed_in_page_type_and_on_the_public_pages_that_have_a_bar_search(): void
    {
        $user = $this->member();

        foreach (['/home', '/questions', '/questions/ask', '/solutions', '/topics', '/library', '/businesses', '/people', '/platforms', '/analytics', '/user/profile/edit', '/notifications', '/inbox'] as $path) {
            $response = $this->actingAs($user)->get($path);
            $this->assertSame(200, $response->getStatusCode(), $path.' answered '.$response->getStatusCode());
            $html = $response->getContent();
            $this->assertMatchesRegularExpression('#<div class="dh-search" id="dh-search" data-dh-search>.*?<input[^>]*id="ix-search-input"#s', $html, $path.' has the header search');
        }

        $this->app['auth']->forgetGuards();

        foreach (['/faqs', '/about', '/features', '/solution-results?search=cash', '/terms'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertMatchesRegularExpression('#<div class="dh-search" id="dh-search" data-dh-search>.*?<input[^>]*id="ix-search-input"#s', $html, $path.' has the header search for a visitor');
        }

        // The landing page is its own search (the hero field is the page), the way in and the error pages are for getting through.
        foreach (['/', '/login', '/register'] as $path) {
            $this->assertStringNotContainsString('data-dh-search>', $this->get($path)->assertOk()->getContent(), $path.' has no header search');
        }
        $landing = $this->get('/')->getContent();
        $this->assertStringContainsString('id="lp-q"', $landing, 'the landing page is a real search form');
    }

    public function test_the_search_is_still_the_same_livewire_component_with_the_same_attributes(): void
    {
        $html = $this->actingAs($this->member())->get('/home')->assertOk()->getContent();
        preg_match('#<input[^>]*id="ix-search-input"[^>]*>#', $html, $input);

        $this->assertNotEmpty($input);
        $this->assertStringContainsString('wire:model.live.debounce.'.(int) config('search.debounce_ms').'ms="query"', $input[0], 'the configured debounce is kept');
        foreach (['wire:keydown.arrow-up.prevent="decrementHighlight"', 'wire:keydown.arrow-down.prevent="incrementHighlight"', 'wire:keydown.enter="openHighlighted"', 'name="search"', 'autocomplete="off"', 'type="search"', 'class="dsr-input"'] as $attribute) {
            $this->assertStringContainsString($attribute, $input[0]);
        }
        $this->assertStringContainsString('action="'.route('solution_search_results').'"', $html, 'the form still goes to the full results page');
        $this->assertStringContainsString('Ctrl K', $html);
    }

    public function test_an_empty_box_suggests_and_a_typed_query_groups_its_rows_in_the_shared_pill_markup(): void
    {
        $user = $this->member();
        Questions::create(['user_id' => $user->id, 'team_id' => $user->current_team_id, 'visibility' => 'team', 'question' => 'How do we onboard a supplier?', 'description' => 'x']);

        $this->actingAs($user);
        $empty = Livewire::test(Search::class);
        $empty->assertSeeHtml('class="dsr-head"')->assertSee('Try asking')->assertSee('Go to')->assertSee('Ask a question')->assertSee('Add a solution')
            ->assertSeeHtml('class="dsr-chip"')->assertSee('Type a reference such as');

        $typed = Livewire::test(Search::class)->set('query', 'onboard');
        $typed->assertSeeHtml('How do we <mark>onboard</mark> a supplier?')->assertSee('Questions')->assertSee('Not here?')->assertSee('Ask “onboard” as a new question')->assertSee('See every result for “onboard”')
            ->assertSeeHtml('dsr-item');

        $none = Livewire::test(Search::class)->set('query', 'zzqxwnothing');
        $none->assertSee('Nothing matches')->assertSeeHtml('class="dsr-foot"');
    }

    public function test_the_header_search_shows_only_the_signed_in_persons_company_and_public_content(): void
    {
        $alice = $this->member('Acme Ltd');
        $gina = User::factory()->create(['name' => 'Gina Globex', 'email_verified_at' => Carbon::now()]);
        $globex = Team::forceCreate(['user_id' => $gina->id, 'name' => 'Globex Corp', 'personal_team' => false]);
        $gina->forceFill(['current_team_id' => $globex->id])->save();

        Questions::create(['user_id' => $alice->id, 'team_id' => $alice->current_team_id, 'visibility' => 'team', 'question' => 'Zebra acme private', 'description' => 'x']);
        Questions::create(['user_id' => $gina->id, 'team_id' => $globex->id, 'visibility' => 'team', 'question' => 'Zebra globex private', 'description' => 'x']);
        Questions::create(['user_id' => $gina->id, 'team_id' => $globex->id, 'visibility' => 'public', 'question' => 'Zebra globex public', 'description' => 'x']);

        $this->actingAs($alice);
        $component = Livewire::test(Search::class)->set('query', 'Zebra');

        $html = $component->html();
        $this->assertStringContainsString('<mark>Zebra</mark> acme private', $html);
        $this->assertStringContainsString('<mark>Zebra</mark> globex public', $html);
        $this->assertStringNotContainsString('globex private', $html, 'another company\'s private question is not in the dropdown markup');

        // A visitor's search finds public content only.
        auth()->logout();
        $this->get('/solution-results?search=Zebra')->assertOk()->assertSee('Zebra globex public')->assertDontSee('Zebra acme private')->assertDontSee('Zebra globex private');
    }

    public function test_no_view_sets_a_literal_pixel_font_size_on_text(): void
    {
        // Sizes are the dot-ui type scale (var(--dot-text-*-size)). Icon spans keep their pixel size; the hand-over page (a strict CSP of its own and
        // system type), the no-build error fallback and the HTML mail theme are the three places that cannot link the shared stylesheet.
        $skipped = ['launch.blade.php', 'errors/_ecosystem-layout.blade.php'];
        foreach ((new Finder)->files()->in(resource_path('views'))->exclude(['components/dot', 'vendor/mail', 'emails'])->name('*.blade.php') as $file) {
            if (in_array($file->getRelativePathname(), $skipped, true)) {
                continue;
            }
            foreach (preg_split('/\R/', $file->getContents()) as $number => $line) {
                if (str_contains($line, 'material-symbols')) {
                    continue;
                }
                $this->assertSame(0, preg_match('/font-size:\s*[\d.]+(px|rem)/', $line), $file->getRelativePathname().':'.($number + 1).' sets a literal font size: use a --dot-text-* step');
            }
        }
    }

    // ---- the tidied sizes and the phone sheet -----------------------------------------------------------------------

    public function test_the_phone_gets_its_own_markup_not_a_squeezed_desktop_bar(): void
    {
        $html = $this->actingAs($this->member())->get('/home')->assertOk()->getContent();

        // The drawer lists every place, the account section is inside it, and the bell stays in the 52px bar (always).
        preg_match('#<div class="dh-drawer".*?</section>#s', $html, $drawer);
        foreach (['Home', 'Knowledge', 'Questions', 'Seminars', 'Network', 'Businesses', 'People', 'Platforms', 'All platforms', 'Analytics', 'Alice Acme', 'Profile', 'Log out'] as $label) {
            $this->assertStringContainsString($label, $drawer[0] ?? '', $label.' is in the drawer');
        }
        $this->assertStringContainsString('dh-bell--always', $html);
        $this->assertStringContainsString('aria-controls="dh-drawer"', $html);
    }

    public function test_dot_apps_is_a_bottom_sheet_on_a_phone_and_keeps_its_own_entitlement_logic(): void
    {
        $css = (string) file_get_contents(resource_path('css/shell.css'));
        $this->assertStringContainsString('.ix-yardscrim', $css);
        $this->assertMatchesRegularExpression('#@media \(max-width: 639px\)\s*\{[^@]*\.ix-yardmap\s*\{[^}]*position:\s*fixed#s', $css, 'a bottom sheet under 640px');

        $view = (string) file_get_contents(resource_path('views/livewire/dot-switcher.blade.php'));
        $this->assertStringContainsString('aria-label="Close Dot Apps"', $view);
        $this->assertStringContainsString("classList.toggle('dot-scroll-locked'", $view, 'the page behind the sheet is locked');
        $this->assertStringContainsString('wire:click="switchTo(', $view, 'platforms still open through the same Livewire action (CompanyCatalogue::openable decides the list)');
    }
}
