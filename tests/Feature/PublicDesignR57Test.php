<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R-57: the public inner pages open on a plate, a heading and a photograph, and stay complete without the motion script.
 * What these tests protect: every lane on Contact points at a real destination, every photograph says who took it, and
 * categories with nobody listed no longer fill the directory.
 */
class PublicDesignR57Test extends TestCase
{
    use RefreshDatabase;

    public function test_contact_offers_a_lane_for_each_reason_and_each_lane_goes_somewhere_real(): void
    {
        $html = $this->get('/contact')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertSame(5, substr_count($html, 'class="pg-route"'));
        foreach (['mailto:support@infodot.co.za', 'mailto:privacy@infodot.co.za', route('complains'), route('security'), route('faqs')] as $href) {
            $this->assertStringContainsString('href="'.$href.'"', $html);
        }
        $this->assertStringNotContainsString('Photograph by', $html, 'credits live on the Photo credits section of the About page, not on the picture');
        $this->assertStringContainsString('Please do not send passwords', $html);
    }

    public function test_the_contact_form_only_appears_when_a_mailbox_is_connected(): void
    {
        config(['mail.contact_to' => null]);
        $this->get('/contact')->assertOk()->assertDontSee('name="website"', false);

        config(['mail.contact_to' => 'hello@example.test']);
        $this->get('/contact')->assertOk()->assertSee('name="website"', false)->assertSee('Send message');
    }

    public function test_every_unsplash_photograph_on_the_pages_is_credited_on_the_about_page(): void
    {
        $about = $this->get('/about')->assertOk()->getContent();

        foreach (['Vije Vijendranath', 'Simon Hurry', 'Mario Scheibl', 'Amy Harrison', 'Clodagh Da Paixao', 'Nigel SB Photography', 'FitNish Media', 'Marc St'] as $author) {
            $this->assertStringContainsString($author, $about, "{$author} is credited");
        }
        $this->assertStringContainsString('unsplash.com/license', $about);
    }

    public function test_the_reading_pages_open_on_a_plate_and_a_photograph(): void
    {
        foreach (['/about', '/features', '/faqs'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();
            $this->assertStringContainsString('class="pg-plate"', $html, $path);
            $this->assertStringContainsString('class="pg-strip"', $html, $path);
            $this->assertStringContainsString('has-motion', $html, $path);
            $this->assertStringContainsString('__motionFailsafe', $html, "{$path}: the layout shows the page if the motion script is late");
        }
    }

    public function test_the_directory_puts_empty_categories_behind_one_disclosure(): void
    {
        $html = $this->get('/businesses')->assertOk()->getContent();

        $this->assertStringContainsString('ix-dir__more-cats', $html);
        $this->assertStringContainsString('more categor', $html);
        $this->assertStringNotContainsString('Photograph by', $html);
    }

    public function test_motion_for_these_pages_only_hides_things_while_the_motion_flag_is_set(): void
    {
        $css = (string) file_get_contents(resource_path('css/infodot.css'));
        preg_match_all('/([^{}]*\[data-pg-(?:in|reveal)\][^{}]*)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);
        $this->assertNotEmpty($rules);
        foreach ($rules as [, $selector, $body]) {
            if (str_contains($body, 'opacity: 0;')) {
                $this->assertStringContainsString('.has-motion', $selector, 'content may only start hidden while the motion script is running');
            }
        }

        $js = (string) file_get_contents(resource_path('js/pages.js'));
        $this->assertStringContainsString('prefers-reduced-motion: reduce', $js);
        $this->assertStringContainsString('motion-failsafe', $js);
        $this->assertStringNotContainsString("addEventListener('scroll'", $js);
    }

    public function test_the_home_hero_has_one_heading_one_search_and_three_scenes_with_a_pause_button(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'id="hero-title"'), 'the words appear once, not once per scene');
        $this->assertSame(1, substr_count($html, 'class="lp-search"'), 'one search box');
        foreach (['a', 'b', 'c'] as $scene) {
            $this->assertStringContainsString('data-scene-id="'.$scene.'"', $html);
        }
        $this->assertStringContainsString('data-hd-pause', $html, 'the changing pictures can always be paused');
        $this->assertStringContainsString('aria-label="Pause the changing pictures"', $html);
        $this->assertStringNotContainsString('Photograph by', $html, 'credits live on the About page');

        $js = (string) file_get_contents(resource_path('js/landing.js'));
        $this->assertStringContainsString('userPaused = reduce', $js, 'nothing changes by itself when motion is reduced');
    }

    public function test_no_photograph_is_used_in_two_places(): void
    {
        $files = array_merge(
            glob(resource_path('views/*.blade.php')) ?: [],
            glob(resource_path('views/partials/home-hero*.blade.php')) ?: [],
            glob(resource_path('views/businesses/index.blade.php')) ?: [],
        );
        $uses = [];
        foreach ($files as $file) {
            preg_match_all('#resources/img/(?:pages|landing)/([a-z0-9-]+?)(?:-\d+)?\.jpg#', (string) file_get_contents($file), $m);
            foreach (array_unique($m[1]) as $name) {
                $uses[$name][] = basename($file);
            }
        }
        foreach ($uses as $name => $where) {
            $this->assertCount(1, array_unique($where), "the photograph {$name} appears in more than one place: ".implode(', ', array_unique($where)));
        }
        $this->assertGreaterThanOrEqual(12, count($uses), 'the pages use a spread of different photographs');
    }
}
