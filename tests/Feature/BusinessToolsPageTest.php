<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\BusinessTools;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R-56: the public Business Tools page. It is a marketing page, so what these tests protect is honesty: every platform on the
 * homepage has a showcase, every fact in a showcase is the catalogue's own word for it, no two platforms share their reasons,
 * and nothing on the page changes what a visitor can open.
 */
class BusinessToolsPageTest extends TestCase
{
    use RefreshDatabase;

    /** Live, public platforms that the homepage wall shows (Central is live but not placed on the homepage yet). */
    private function homepageWall(): array
    {
        return collect(config('ecosystem.platforms'))
            ->filter(fn (array $p, string $key) => ($p['active'] ?? false) && ! ($p['internal'] ?? false) && ! in_array($key, ['infodot', 'agents', 'central'], true))
            ->keys()->sort()->values()->all();
    }

    /** The visible words of the page's main area: no scripts, no markup. */
    private function visibleText(string $html): string
    {
        preg_match('/<main id="main".*?<\/main>/s', $html, $main);
        $text = preg_replace('#<(script|style)\b.*?</\1>#s', ' ', $main[0] ?? '');

        return preg_replace('/\s+/', ' ', html_entity_decode(strip_tags((string) $text), ENT_QUOTES | ENT_HTML5));
    }

    public function test_the_page_opens_for_a_visitor_with_its_own_title_description_and_one_heading(): void
    {
        $html = $this->get('/business-tools')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('<title>Business tools · InfoDot</title>', $html);
        $this->assertStringContainsString('<meta name="description" content="Discover the connected business tools', $html);
        $this->assertStringContainsString('Your business deserves more than disconnected tools.', $html);
        $this->assertStringContainsString('"@type":"CollectionPage"', $html);
        $this->assertStringContainsString('has-motion', $html);
        $this->assertStringContainsString('__motionFailsafe', $html, 'the layout shows the page if the motion script is late');
    }

    public function test_the_page_states_infodots_aim_as_an_aim(): void
    {
        $html = $this->get(route('business-tools'))->assertOk()->getContent();

        // R-70: the owner's aim, worded as an aim and shown once, before the closing invitation; never as a figure plate.
        $this->assertStringContainsString('Our aim is to help <span class="hp-aim__mark">one million businesses every year</span> reach the goals they set for themselves.', $html);
        $this->assertSame(1, substr_count($html, 'class="hp-aim"'));
        $this->assertLessThan(strpos($html, 'id="bt-cta-title"'), strpos($html, 'id="bt-aim-title"'));
        $this->assertStringNotContainsString('1,000,000', $html);
    }

    public function test_every_platform_on_the_homepage_wall_has_its_own_showcase(): void
    {
        $html = $this->get('/business-tools')->assertOk()->getContent();
        $shown = array_keys(BusinessTools::page()['tools']);
        sort($shown);

        $this->assertSame($this->homepageWall(), $shown, 'the Business Tools page shows exactly the platforms the homepage wall shows');
        foreach ($shown as $key) {
            $this->assertStringContainsString('id="tool-'.$key.'"', $html);
        }
        $this->assertSame(count($shown), substr_count($html, 'data-tool'), 'one showcase per platform');
    }

    public function test_every_showcase_answers_the_five_questions(): void
    {
        $html = $this->get('/business-tools')->assertOk()->getContent();
        $text = $this->visibleText($html);

        foreach (BusinessTools::page()['tools'] as $key => $tool) {
            // What is it, what problem does it solve, how does it help, why would I need it, how does it help the business grow, and what next.
            foreach (['tagline', 'headline', 'problem', 'why', 'growth', 'outcome'] as $part) {
                $this->assertStringContainsString($tool[$part] ?? '', $text, "{$key} is missing its {$part}");
            }
            $this->assertGreaterThanOrEqual(3, count($tool['capabilities']), "{$key} lists fewer than three capabilities");
            $this->assertLessThanOrEqual(5, count($tool['capabilities']), "{$key} lists more than five capabilities");
            $this->assertNotSame([], $tool['pillars'], "{$key} serves none of Discover, Manage and Grow");
        }
    }

    public function test_the_facts_are_the_catalogues_own_words(): void
    {
        $catalogue = BusinessTools::catalogue();

        foreach (BusinessTools::page()['tools'] as $key => $tool) {
            $entry = $catalogue[$key];
            $this->assertSame($entry['name'], $tool['name']);
            $this->assertSame($entry['tagline'], $tool['tagline']);
            $this->assertSame(array_slice($entry['capabilities'], 0, BusinessTools::MAX_CAPABILITIES), $tool['capabilities'], "{$key}: capabilities must be the catalogue's, in its order");
            $declared = array_values(array_diff((array) $entry['connects_with'], ['infodot']));
            $this->assertSame($declared, array_column($tool['connects'], 'key'), "{$key}: only connections the catalogue declares");
        }
    }

    public function test_no_two_platforms_share_a_headline_a_reason_or_a_growth_story(): void
    {
        foreach (['headline', 'problem', 'why', 'growth', 'outcome'] as $part) {
            $values = array_column(BusinessTools::page()['tools'], $part);
            $this->assertSame(count($values), count(array_unique($values)), "two platforms share their {$part}");
        }
    }

    public function test_the_copy_keeps_the_house_rules(): void
    {
        $text = $this->visibleText($this->get('/business-tools')->assertOk()->getContent());

        $this->assertDoesNotMatchRegularExpression('/[\x{2013}\x{2014}]/u', $text, 'no em or en dashes in visible text');
        foreach (['seamless', 'elevate', 'unleash', 'cutting-edge', 'leverage', 'supercharge', 'game-changing', 'revolutionis', 'revolutioniz', 'best-in-class'] as $word) {
            $this->assertStringNotContainsStringIgnoringCase($word, $text, "the page uses the filler word {$word}");
        }
        // Nothing invented: no figures about customers, savings or speed, and no claim of a guarantee.
        foreach (['guarantee', 'trusted by', 'thousands of', 'millions of', '% faster', 'testimonial'] as $claim) {
            $this->assertStringNotContainsStringIgnoringCase($claim, $text, "the page makes the claim: {$claim}");
        }
    }

    public function test_every_link_inside_the_page_lands_on_something_and_every_connection_is_a_platform(): void
    {
        $html = $this->get('/business-tools')->assertOk()->getContent();
        preg_match_all('/href="#([a-z0-9-]+)"/', $html, $anchors);
        foreach (array_unique($anchors[1]) as $id) {
            $this->assertStringContainsString('id="'.$id.'"', $html, "#{$id} points at nothing");
        }
        foreach (BusinessTools::page()['tools'] as $key => $tool) {
            foreach ($tool['connects'] as $link) {
                $this->assertArrayHasKey($link['key'], config('ecosystem.platforms'), "{$key} names {$link['key']}, which is not a platform");
            }
        }
    }

    public function test_the_calls_to_action_respect_how_access_works(): void
    {
        $guest = $this->get('/business-tools')->assertOk()->getContent();
        $this->assertStringContainsString('href="'.route('register').'"', $guest);
        $this->assertStringNotContainsString(route('platforms.show', 'finance'), $guest, 'a visitor is never sent to a page that needs an account');
        $this->assertStringContainsString('Get started with Dot.Finance', $guest);
        $this->assertStringContainsString('Explore Dot.HR', $guest);

        $member = $this->actingAs(User::factory()->create())->get('/business-tools')->assertOk()->getContent();
        $this->assertStringContainsString('href="'.route('platforms.show', 'hr').'"', $member, 'a member goes to the platform page, where access is decided');
        $this->assertStringContainsString('Open your platforms', $member);
    }

    public function test_retired_and_internal_platforms_never_appear(): void
    {
        $html = $this->get('/business-tools')->assertOk()->getContent();

        foreach (['Dot.Billing', 'Dot.Farms', 'Dot.Plug', 'Dot.Memory', 'Dot.Dopemine', 'Dot.Agents'] as $name) {
            $this->assertStringNotContainsString($name, $html, $name);
        }
    }

    public function test_the_screens_are_the_platforms_own_bundled_screens_and_say_so(): void
    {
        $html = $this->get('/business-tools')->assertOk()->getContent();

        foreach (BusinessTools::page()['tools'] as $key => $tool) {
            $this->assertStringContainsString('alt="A screen from '.$tool['name'].', shown with demonstration data"', $html);
            $this->assertStringContainsString($tool['name'].', with demonstration data', $html, 'each screen carries an honest caption');
            $this->get(route('branding.asset', ['slug' => $key, 'file' => 'hero-960.webp']))->assertOk()->assertHeader('Content-Type', 'image/webp');
        }
    }

    public function test_the_finder_points_at_shown_platforms_and_changes_nothing(): void
    {
        $html = $this->get('/business-tools')->assertOk()->getContent();
        preg_match('/<section class="bt-finder".*?<\/section>/s', $html, $finder);

        $this->assertStringContainsString('What does your business need help with?', $finder[0]);
        $this->assertStringNotContainsString('<form', $finder[0], 'the finder is a set of links, not a form');
        $this->assertStringNotContainsString('method="post"', strtolower($finder[0]));
        foreach (BusinessTools::page()['needs'] as $need) {
            foreach ($need['tools'] as $key) {
                $this->assertArrayHasKey($key, BusinessTools::page()['tools']);
            }
        }
        $this->assertGreaterThanOrEqual(6, count(BusinessTools::page()['needs']));
    }

    public function test_the_homepage_button_opens_this_page_and_the_sitemap_lists_it(): void
    {
        $this->get('/')->assertOk()->assertSee('href="'.route('business-tools').'"', false)->assertSee('Explore Business Tools');
        $this->get('/sitemap.xml')->assertOk()->assertSee(route('business-tools'), false);
    }

    public function test_each_platform_sits_in_a_group_that_matches_what_it_does(): void
    {
        $groups = collect(BusinessTools::page()['groups'])->keyBy('key');
        $keys = fn (string $group): array => $groups[$group]['tools'];

        $this->assertSame(['finance'], $keys('money'));
        $this->assertSame(['hr'], $keys('people'));
        $this->assertContains('pulse', $keys('customers'), 'Pulse introduces businesses, so it sits with customers and partners');
        $this->assertNotContains('pulse', $keys('understand'));
        $this->assertEqualsCanonicalizing(['mines', 'ehail', 'tutor'], $keys('specialised'));
        $placed = collect($groups)->flatMap(fn ($g) => $g['tools'])->all();
        $this->assertSame(count($placed), count(array_unique($placed)), 'a platform sits in one group only');
    }

    public function test_motion_only_hides_things_while_the_motion_flag_is_set_and_respects_reduced_motion(): void
    {
        $css = (string) file_get_contents(resource_path('css/infodot.css'));
        preg_match_all('/([^{}]*\[data-bt-hero\][^{}]*)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);
        $this->assertNotEmpty($rules);
        foreach ($rules as [, $selector, $body]) {
            if (str_contains($body, 'opacity: 0;')) {
                $this->assertStringContainsString('.has-motion', $selector, 'content may only start hidden while the motion script is running');
            }
        }

        $js = (string) file_get_contents(resource_path('js/tools.js'));
        $this->assertStringContainsString('prefers-reduced-motion: reduce', $js);
        $this->assertStringContainsString("classList.contains('has-motion')", $js);
        $this->assertStringContainsString('motion-failsafe', $js);
        $this->assertStringNotContainsString('scroll-behavior', $js, 'no scroll hijacking');
        $this->assertStringNotContainsString("addEventListener('scroll'", $js, 'scroll work goes through ScrollTrigger, not a scroll listener');
    }

    public function test_the_page_is_complete_without_script(): void
    {
        $html = $this->get('/business-tools')->assertOk()->getContent();

        // The longer half of every showcase is on the page as plain markup (a phone toggles it with a checkbox, no script).
        $this->assertSame(count(BusinessTools::page()['tools']), substr_count($html, 'class="bt-more__check"'));
        foreach (BusinessTools::page()['tools'] as $tool) {
            foreach ($tool['capabilities'] as $capability) {
                $this->assertStringContainsString(e($capability), $html);
            }
        }
        $this->assertStringNotContainsString('style="opacity:0', $html, 'nothing is hidden by inline style in the server markup');
    }
}
