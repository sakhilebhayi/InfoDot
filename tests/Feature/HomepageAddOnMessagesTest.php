<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R-75 phase 1: add-on messages on the homepage ("Business is hard enough. Getting the support you need shouldn't be."). They are
 * additions: the hero, the story sections and the journey tabs keep their own words, and every new link is a real public page.
 */
class HomepageAddOnMessagesTest extends TestCase
{
    use RefreshDatabase;

    private function html(): string
    {
        return html_entity_decode((string) $this->get('/')->assertOk()->getContent(), ENT_QUOTES | ENT_HTML5);
    }

    public function test_the_brand_promise_is_said_once_with_its_honest_second_line(): void
    {
        $html = $this->html();

        $this->assertSame(1, substr_count(strip_tags($html), "Business is hard enough. Getting the support you need shouldn't be."));
        $this->assertStringContainsString("We can't make running a business easy. We can make it easier to find the help that moves it forward.", $html);
        $this->assertStringContainsString('A better, more connected way to build your business.', $html, 'The owner-approved hero heading stays.');
    }

    public function test_someone_who_does_not_know_what_they_need_has_a_path(): void
    {
        $html = $this->html();

        $this->assertStringContainsString("You're not sure what you need yet", $html);
        $this->assertStringContainsString('Ask in plain words, or see what other businesses needed first.', $html);
    }

    public function test_every_journey_stage_carries_one_feeling_line(): void
    {
        $html = $this->html();

        foreach ([
            "You don't need everything figured out before you begin.",
            'Fewer places to look, so the day-to-day feels more organised.',
            "The people you need are easier to find when they're in one place.",
            'Progress one step at a time, with the numbers that matter in view.',
            'Ways to put your skills to work, without needing a business plan first.',
        ] as $line) {
            $this->assertStringContainsString($line, $html);
        }
    }

    public function test_check_infodot_first_links_to_four_real_public_pages(): void
    {
        $html = $this->html();

        $this->assertStringContainsString('When something comes up, check InfoDot first.', $html);
        $this->assertSame(1, preg_match('#<section class="hp-first".*?</section>#s', $html, $strip));

        foreach ([route('solution_search_results'), route('business-tools'), route('businesses.index'), route('solution_search_results', ['topic' => 'starting-a-business'])] as $url) {
            $this->assertStringContainsString('href="'.$url.'"', $strip[0]);
            $this->get($url)->assertOk();
        }
    }

    public function test_the_close_invites_any_stage_and_the_existing_guards_still_hold(): void
    {
        $html = $this->html();

        $this->assertStringContainsString('Whatever stage your business is at, InfoDot is a good place to start.', $html);
        $this->assertSame(2, substr_count($html, 'Join InfoDot for free'));
        $this->assertStringNotContainsStringIgnoringCase('guarantee', $html);
        $this->assertStringNotContainsString('—', strip_tags($html));
    }
}
