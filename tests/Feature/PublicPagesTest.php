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

        $this->assertStringContainsString('Find practical business solutions.', $html);
        $this->assertStringContainsString('role="search"', $html);
        $this->assertStringContainsString('name="search"', $html);
        $this->assertStringContainsString('has-motion', $html);
        $this->assertStringContainsString('prefers-reduced-motion', $html);
        $this->assertStringContainsString('knowledge base for a business', $html);
        $this->assertStringContainsString(route('register'), $html);
        $this->assertStringContainsString(route('solution_search_results'), $html);
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('<title>InfoDot: build your business with collective intelligence</title>', $html);
        $this->assertStringContainsString('<meta name="description"', $html);
    }

    public function test_the_landing_page_makes_no_claim_that_is_not_true(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        foreach (['PostgreSQL', 'shared database', 'likes on', 'Associates', 'guarantee', 'testimonial', 'compliant', 'certified', 'trusted by', 'our customers', 'founder'] as $claim) {
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

    public function test_no_public_page_names_a_person(): void
    {
        foreach (['/', '/about', '/features', '/faqs', '/complains', '/contact'] as $path) {
            $this->assertDoesNotMatchRegularExpression('/sakhile|bhayi/i', $this->get($path)->getContent(), $path);
        }
    }

    public function test_the_photographs_are_credited_on_the_about_page_and_linked_from_the_footer(): void
    {
        $about = $this->get('/about')->assertOk()->getContent();

        foreach (['WAO Coworking', 'Rwebogora', 'Metchyves', 'Matti Blume', 'CC BY-SA 4.0'] as $credit) {
            $this->assertStringContainsString($credit, $about);
        }
        $this->assertStringContainsString('id="photo-credits"', $about);
        $this->assertStringContainsString('/about#photo-credits', $this->get('/')->getContent());
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

        $this->get('/contact')->assertOk()->assertDontSee('Send message')->assertSee('privacy@infodot.co.za');

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

    public function test_every_search_button_is_the_arrow_from_the_infodot_logo(): void
    {
        $landing = $this->get('/')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/class="lp-search__go"[^>]*aria-label="Search"[^>]*><span class="ix-arrow"/', $landing);

        $results = $this->get('/solution-results?search=register')->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/class="ix-btn ix-btn--arrow"[^>]*aria-label="Search"[^>]*><span class="ix-arrow"/', $results);
        $this->assertMatchesRegularExpression('/class="ix-search__go"[^>]*aria-label="Search questions and solutions"[^>]*>\s*<span class="ix-arrow"/', $results, 'the top-bar search');
        $this->assertStringContainsString('--ix-arrow-url', $results);
        $this->assertFileExists(resource_path('img/arrow-mask.png'));
    }

    public function test_the_platforms_on_the_landing_page_are_shown_as_logos_with_their_names_as_alt_text(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('class="lp-logos"', $html);
        foreach (['Dot.Files', 'Dot.Analytics', 'Dot.Tasks'] as $name) {
            $this->assertStringContainsString('alt="'.$name.'"', $html);
        }
        $this->assertStringNotContainsString('class="ix-platlist"', $html, 'no longer a list of names');
        $this->assertStringNotContainsString('alt="Dot.Agents"', $html, 'the one mark without wording is not in the wall');
    }

    public function test_the_visitor_tab_row_has_only_the_two_search_oriented_links(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        preg_match('/<nav class="ix-tabs" aria-label="About InfoDot">(.*?)<\/nav>/s', $html, $m);

        $this->assertStringContainsString('Questions people ask', $m[1]);
        $this->assertStringContainsString('Browse public answers', $m[1]);
        $this->assertStringNotContainsString('What it does', $m[1]);
        $this->assertStringNotContainsString('>About<', $m[1]);
        $this->assertStringContainsString('>About</a>', $html, 'About is still in the footer');
    }

    public function test_the_legal_pages_use_the_site_shell_with_a_contents_list_and_keep_their_words(): void
    {
        foreach (['/policy' => ['Privacy Policy', 'Who we are', 'BluePin Inc'], '/terms' => ['Terms &amp; Conditions', 'Your account', 'BluePin'], '/cookies' => ['Cookie Policy', 'Cookies we set', 'infodot_session']] as $path => [$title, $heading, $text]) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertStringContainsString('class="ix-top ix-top--public"', $html, $path);
            $this->assertStringContainsString('class="ix-legal"', $html, $path);
            $this->assertStringContainsString('aria-label="On this page"', $html, $path);
            $this->assertSame(1, substr_count($html, '<h1'), $path);
            $this->assertStringContainsString($title, $html, $path);
            $this->assertMatchesRegularExpression('/<h2 id="[a-z0-9-]+">[^<]*'.preg_quote($heading, '/').'/', $html, $path);
            $this->assertStringContainsString($text, $html, $path);
            $this->assertStringContainsString('Last updated', $html, $path);
        }
        $this->assertStringContainsString('class="ix-tablewrap"', $this->get('/cookies')->getContent(), 'the cookie table scrolls inside itself on a phone');
    }

    public function test_every_contents_link_points_at_a_heading_that_exists(): void
    {
        $html = $this->get('/policy')->assertOk()->getContent();
        preg_match_all('/<a href="#([a-z0-9-]+)">/', $html, $links);

        $this->assertNotEmpty($links[1]);
        foreach ($links[1] as $id) {
            $this->assertStringContainsString('<h2 id="'.$id.'">', $html);
        }
    }

    public function test_the_landing_images_and_the_arrow_are_part_of_the_build_that_the_deploy_publishes(): void
    {
        // Only public/build is copied to the document root on deploy; an image kept anywhere else in public/ would be a 404 on the live site.
        $manifestPath = public_path('build/manifest.json');
        if (! is_file($manifestPath)) {
            $this->markTestSkipped('No built manifest in this environment.');
        }
        $manifest = json_decode((string) file_get_contents($manifestPath), true);

        foreach (['resources/img/arrow-mask.png', 'resources/img/landing/hero-1920.jpg', 'resources/img/landing/hero-960.jpg', 'resources/img/landing/seamstress.jpg', 'resources/img/landing/couturiere.jpg', 'resources/img/landing/market.jpg', 'resources/img/landing/platforms/files.png'] as $key) {
            $this->assertArrayHasKey($key, $manifest, $key.' is not in the build, so the live site would not have it');
        }
        $this->assertDirectoryDoesNotExist(public_path('img/landing'), 'landing images belong in resources/img so they ship with the build');
        $this->assertFileDoesNotExist(public_path('img/arrow-mask.png'));
    }
}
