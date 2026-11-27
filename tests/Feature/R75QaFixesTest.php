<?php

namespace Tests\Feature;

use App\Models\Topic;
use App\Services\Knowledge\TopicService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R-75 phase 3: what the live QA and the three-visitor review found, fixed.
 */
class R75QaFixesTest extends TestCase
{
    use RefreshDatabase;

    public function test_discontinued_platform_topics_are_switched_off_and_leave_the_filter(): void
    {
        foreach (['billing' => 'Dot.Billing', 'farms' => 'Dot.Farms', 'plug' => 'Dot.Plug'] as $slug => $name) {
            Topic::query()->updateOrCreate(['slug' => $slug], ['name' => $name, 'description' => 'x', 'active' => true, 'sort_order' => 999]);
        }

        (require database_path('migrations/2026_11_27_180000_retire_discontinued_platform_topics.php'))->up();

        $this->assertSame(0, Topic::query()->whereIn('slug', ['billing', 'farms', 'plug'])->where('active', true)->count());
        $this->assertEmpty(app(TopicService::class)->choices()->whereIn('slug', ['billing', 'farms', 'plug']));
        $this->assertStringNotContainsString('Dot.Farms', (string) $this->get(route('solution_search_results'))->getContent());
    }

    public function test_the_first_steps_links_go_to_the_topic_that_has_solutions(): void
    {
        $html = html_entity_decode((string) $this->get('/')->getContent(), ENT_QUOTES | ENT_HTML5);

        $this->assertSame(2, substr_count($html, route('solution_search_results', ['topic' => 'starting-a-business'])));
        $this->assertStringNotContainsString('topic=getting-started', $html);
    }

    public function test_an_established_organisation_is_sent_to_business_tools(): void
    {
        $html = html_entity_decode((string) $this->get('/')->getContent(), ENT_QUOTES | ENT_HTML5);

        $this->assertMatchesRegularExpression('#<a href="'.preg_quote(route('business-tools'), '#').'"><strong>You lead an established organisation#', $html);
    }

    public function test_features_links_to_business_tools_not_a_missing_anchor(): void
    {
        $html = (string) $this->get('/features')->getContent();

        $this->assertStringNotContainsString('#tools"', $html);
        $this->assertStringContainsString('href="'.route('business-tools').'"', $html);
    }

    public function test_an_empty_public_search_suggests_words_to_try(): void
    {
        $text = html_entity_decode(strip_tags((string) $this->get(route('solution_search_results'))->getContent()), ENT_QUOTES | ENT_HTML5);

        $this->assertStringContainsString("Or try a word such as register, tax or pricing to search InfoDot's researched solutions.", $text);
    }
}
