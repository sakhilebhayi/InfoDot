<?php

namespace Tests\Feature;

use App\Mail\ContactMessage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** The pages a visitor can open: what InfoDot is today, said plainly, and a contact form that never accepts a message it cannot deliver. */
class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_landing_page_says_what_infodot_is_and_what_it_does_today(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('Build your business with collective intelligence.', $html);
        $this->assertStringContainsString('knowledge base for a business', $html);
        $this->assertStringContainsString('Start building your business', $html);
        $this->assertStringContainsString('Explore questions and answers', $html);
        $this->assertStringContainsString(route('register'), $html);
        $this->assertStringContainsString(route('solution_search_results'), $html);
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('<title>InfoDot: build your business with collective intelligence</title>', $html);
        $this->assertStringContainsString('<meta name="description"', $html);
    }

    public function test_the_landing_page_makes_no_claim_that_is_not_true(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        foreach (['PostgreSQL', 'shared database', 'likes on', 'Associates', 'guarantee', 'testimonial', 'compliant', 'certified', 'customers'] as $claim) {
            $this->assertStringNotContainsStringIgnoringCase($claim, strip_tags($html), "the page must not say: {$claim}");
        }
    }

    public function test_the_other_public_pages_have_real_content_a_heading_and_a_title(): void
    {
        $pages = [
            '/about' => ['About InfoDot', 'Where things stand'],
            '/features' => ['What InfoDot does', 'Company first, public by choice'],
            '/faqs' => ['Questions people ask', 'Who can see my question?'],
            '/complains' => ['How to complain', 'Information Regulator'],
            '/contact' => ['Contact us', 'privacy@infodot.co.za'],
        ];

        foreach ($pages as $path => [$heading, $text]) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, '<h1'), $path);
            $this->assertStringContainsString($heading, $html, $path);
            $this->assertStringContainsString($text, $html, $path);
            $this->assertStringContainsString('class="ix-top ix-top--public"', $html, $path);
        }
    }

    public function test_the_public_pages_use_no_pronoun_for_the_founder(): void
    {
        foreach (['/', '/about'] as $path) {
            $text = strip_tags($this->get($path)->getContent());
            $this->assertDoesNotMatchRegularExpression('/\b(he|his|him|she|her)\b/i', $text, $path);
        }
    }

    public function test_without_a_mailbox_the_contact_form_is_not_offered_and_a_post_is_refused_honestly(): void
    {
        config(['mail.contact_to' => null]);
        Mail::fake();

        $this->get('/contact')->assertOk()->assertDontSee('Send message')->assertSee('info@sakhilebhayi.com');

        $response = $this->post('/contact-send', ['name' => 'Thandi', 'email' => 't@example.test', 'message' => 'Hello, can you help me?']);

        $response->assertRedirect(route('contact'));
        $response->assertSessionHasErrors('message');
        $response->assertSessionMissing('success');
        Mail::assertNothingSent();
    }

    public function test_with_a_mailbox_a_message_is_delivered_with_the_sender_as_reply_to(): void
    {
        config(['mail.contact_to' => 'inbox@example.test']);
        Mail::fake();

        $this->get('/contact')->assertOk()->assertSee('Send message');
        $this->post('/contact-send', ['name' => 'Thandi', 'email' => 't@example.test', 'message' => 'Hello, can you help me?'])
            ->assertRedirect(route('contact'))->assertSessionHas('success');

        Mail::assertSent(ContactMessage::class, fn (ContactMessage $m) => $m->hasTo('inbox@example.test') && $m->hasReplyTo('t@example.test') && $m->body === 'Hello, can you help me?');
    }

    public function test_the_contact_form_checks_its_input_and_ignores_bots(): void
    {
        config(['mail.contact_to' => 'inbox@example.test']);
        Mail::fake();

        $this->post('/contact-send', ['name' => '', 'email' => 'nope', 'message' => 'short'])->assertSessionHasErrors(['name', 'email', 'message']);
        $this->post('/contact-send', ['name' => 'Bot', 'email' => 'b@example.test', 'message' => 'Buy things now please', 'website' => 'http://spam.test'])->assertSessionHasErrors('website');

        Mail::assertNothingSent();
    }

    public function test_a_failed_send_is_reported_to_the_person_and_not_called_a_success(): void
    {
        config(['mail.contact_to' => 'inbox@example.test']);
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('smtp down'));

        $this->post('/contact-send', ['name' => 'Thandi', 'email' => 't@example.test', 'message' => 'Hello, can you help me?'])
            ->assertSessionHasErrors('message')->assertSessionMissing('success');
    }
}
