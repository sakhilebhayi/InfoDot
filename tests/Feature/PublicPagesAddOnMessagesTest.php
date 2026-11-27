<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * R-75 phase 2: add-on lines on the public pages. They add to each page's own words, never replace them.
 */
class PublicPagesAddOnMessagesTest extends TestCase
{
    use RefreshDatabase;

    private const PROMISE = "Business is hard enough. Getting the support you need shouldn't be.";

    private function text(string $url): string
    {
        return html_entity_decode(strip_tags((string) $this->get($url)->assertOk()->getContent()), ENT_QUOTES | ENT_HTML5);
    }

    public function test_registration_says_what_happens_next_and_keeps_its_own_words(): void
    {
        $text = $this->text('/register');

        $this->assertStringContainsString('One account for the knowledge, tools and businesses on InfoDot. It takes a minute.', $text);
        $this->assertStringContainsString("Nothing to set up first. Once you're in, ask a question, read a solution or open a tool.", $text);
    }

    public function test_about_opens_why_it_exists_with_the_promise(): void
    {
        $text = $this->text('/about');

        $this->assertMatchesRegularExpression('/Why it exists\s*'.preg_quote(self::PROMISE, '/').'/', $text);
        $this->assertStringContainsString('None of this is a lack of ambition.', $text);
    }

    public function test_features_invites_starting_with_one_thing(): void
    {
        $this->assertStringContainsString('Start with whichever one you need first.', $this->text('/features'));
    }

    public function test_the_faq_answers_someone_who_does_not_know_what_they_need(): void
    {
        $html = (string) $this->get('/faqs')->assertOk()->getContent();
        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5);

        $this->assertStringContainsString("I don't know what I need yet. Where do I start?", strip_tags($decoded));
        $this->assertStringContainsString('You do not need everything figured out before you join.', strip_tags($decoded));
        $this->assertMatchesRegularExpression('/"name":\s*"I don\\\\u0027t know what I need yet|"name":\s*"I don\'t know what I need yet/', $decoded, 'The FAQ structured data carries the new question too.');
    }

    public function test_the_footer_carries_the_promise_for_visitors_only(): void
    {
        $this->assertStringContainsString(self::PROMISE, $this->text('/faqs'));

        $this->actingAs(User::factory()->withPersonalTeam()->create());
        $this->assertStringNotContainsString(self::PROMISE, $this->text('/faqs'));
    }
}
