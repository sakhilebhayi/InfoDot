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

        $this->assertStringContainsString('A better, more connected way to build your business.', $html);
        $this->assertStringContainsString('role="search"', $html);
        $this->assertStringContainsString('name="search"', $html);
        $this->assertStringContainsString('has-motion', $html);
        // R-49: the timer that shows the page lives in the layout, so a slow or failed motion script cannot leave it blank.
        $this->assertStringContainsString('__motionFailsafe', $html);
        $this->assertStringContainsString("classList.add('motion-failsafe')", $html);
        $this->assertStringContainsString('prefers-reduced-motion', $html);
        // R-67: the story under the owner's hero: the reality (the owner's own heading), four pillars, a week, what opens when you join.
        foreach (['Knowledge', 'Tools', 'Connections', 'Progress', 'Stop figuring it out alone', 'Stop juggling disconnected apps', "Stop missing what you can't see", 'Turn what you learn into what you do',
            // R-73 phase 1: the promise, the dream it is for, and the reality, in the owner's approved words.
            'Join InfoDot for free', 'Explore the ecosystem', "That business you've been dreaming about? Let's give it somewhere to grow.",
            'InfoDot brings business knowledge, tools and other businesses together in one free account.',
            'Every business starts with someone who decided to try.', "Wherever you're starting from, there's a next step.",
            'You started this business to build something. Not to spend every day figuring everything out.', "None of this means you're doing it wrong.",
            'What a week with InfoDot can look like.', 'Your business. Your tools. One connected ecosystem.', 'What opens when you join.', 'Creating an InfoDot account is free.',
            'Build your business with InfoDot in your corner.',
            'Our aim is to help one million businesses every year reach the goals they set for themselves.'] as $words) {
            $this->assertStringContainsString($words, strip_tags(str_replace('&#039;', "'", $html)), $words);
        }
        // R-71: the hero's third picture labels its tiles with the same words as the pillars.
        foreach (['>Knowledge</span>', '>Tools</span>', '>Connections</span>', 'Picture 3 of 3: knowledge, tools and connections'] as $tile) {
            $this->assertStringContainsString($tile, $html, $tile);
        }
        foreach (['>Discover</span>', '>Manage</span>', '>Grow</span>', 'Discover, Manage and Grow'] as $old) {
            $this->assertStringNotContainsString($old, $html, $old);
        }
        // The old frame and the sections it replaced are gone, so the page does not say the same thing twice.
        foreach (['Most small businesses run on memory and chat.', "You don't have to figure it all out yourself.", 'Getting started with InfoDot is simple.', 'Your business journey starts here.', 'Learn what works',
            // R-73: the R-67 reality heading is replaced, and the ambition section is not said twice.
            "most businesses don't really know what they're doing", 'InfoDot is here to change that.', "Wherever you are, there's a next step.", 'See how it works'] as $old) {
            $this->assertStringNotContainsString($old, strip_tags(str_replace('&#039;', "'", $html)), $old);
        }
        $this->assertStringContainsString(route('register'), $html);
        $this->assertStringContainsString(route('solution_search_results'), $html);
        $this->assertSame(1, substr_count($html, '<h1'));
        $this->assertStringContainsString('<title>InfoDot: free business answers and connected tools</title>', $html);
        $this->assertStringContainsString('<meta name="description"', $html);
    }

    public function test_the_landing_page_keeps_its_search_bar_and_every_anchor_it_links_to(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        // The search box is part of the hero and stays there, above the two buttons.
        $this->assertMatchesRegularExpression('/class="lp-hero hd".*role="search".*class="hp-hero__actions"/s', $html);
        // Every in-page link has somewhere to land (R-73 moves sections; a link left pointing at a removed one would go nowhere).
        preg_match_all('/href="#([a-z0-9-]+)"/', $html, $links);
        $this->assertNotEmpty($links[1]);
        foreach (array_unique($links[1]) as $id) {
            $this->assertStringContainsString('id="'.$id.'"', $html, "the page links to #{$id} but has no such section");
        }
        $this->assertStringContainsString('href="#ecosystem"', $html, 'the hero offers a way to explore the ecosystem');
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_every_hero_part_the_css_hides_is_revealed_by_the_hero_timeline(): void
    {
        // R-73: the CSS hides every [data-hero] element while motion runs, and landing.js reveals them by class name. A part added to
        // the hero without a line in the timeline stays invisible (the context line did, until this test).
        $copy = (string) file_get_contents(resource_path('views/partials/home-hero-copy.blade.php'));
        $timeline = (string) file_get_contents(resource_path('js/landing.js'));

        preg_match_all('/<[a-z0-9]+ class="([a-z0-9_-]+)[^"]*"[^>]*\sdata-hero[\s>]/', $copy, $parts);
        $this->assertNotEmpty($parts[1]);
        foreach (array_unique($parts[1]) as $class) {
            $this->assertStringContainsString("'.{$class}'", $timeline, "the hero hides .{$class} but the timeline never reveals it");
        }
        $this->assertStringContainsString('.has-motion.motion-failsafe [data-hero]', (string) file_get_contents(resource_path('css/infodot.css')), 'the failsafe brings every hero part back');
    }

    public function test_the_tools_are_grouped_by_goal_and_only_show_platforms_that_exist(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        foreach (['Understand your business', 'Manage your money', 'Manage your people', 'Organise your work', 'Create and collaborate', 'Communicate and connect', 'Access specialised solutions'] as $goal) {
            $this->assertStringContainsString($goal, $html, $goal);
        }
        // Retired platforms are never shown, and nothing implies every tool is in every account.
        foreach (['Dot.Billing', 'Dot.Farms', 'Dot.Plug'] as $retired) {
            $this->assertStringNotContainsString($retired, $html, $retired);
        }
        $this->assertStringContainsString('not every tool comes with every account', $html);
    }

    public function test_the_page_keeps_community_answers_accepted_solutions_and_verified_information_apart(): void
    {
        $text = strip_tags($this->get('/')->assertOk()->getContent());

        $this->assertStringContainsString('A community answer is not the same as an accepted solution', $text);
        $this->assertStringContainsString('Nothing is shown as verified unless it has been checked', $text);
    }

    public function test_the_other_public_pages_carry_the_same_message_as_the_landing_page(): void
    {
        $about = strip_tags($this->get('/about')->assertOk()->getContent());
        $features = strip_tags($this->get('/features')->assertOk()->getContent());
        $faqs = strip_tags(str_replace('&#039;', "'", $this->get('/faqs')->assertOk()->getContent()));

        // R-67: Knowledge, Tools, Connections and Progress are the frame everywhere, and nobody is told they need a company or experience to join.
        foreach (['Knowledge.', 'Tools.', 'Connections.', 'Progress.', 'You do not need a company, a plan or any experience to join.', 'None of this is a lack of ambition. It is a lack of access', 'Most businesses are figuring it out as they go.'] as $words) {
            $this->assertStringContainsString($words, $about, $words);
        }
        foreach (['Knowledge: stop figuring it out alone', 'Tools: stop juggling disconnected apps', "Connections: stop missing what you can't see", 'Progress: turn what you learn into what you do', 'Company first, public by choice', 'not every tool comes with every account'] as $words) {
            $this->assertStringContainsString($words, $features, $words);
        }
        foreach (['What can I do with InfoDot?', 'Which business tools can I use?', 'What does it cost to join?', 'Creating an account is free.', 'writes down how it works'] as $words) {
            $this->assertStringContainsString($words, $faqs, $words);
        }
        foreach ([$about, $features] as $page) {
            foreach (['Discover: learn what works', 'Grow: build your future', 'Discover. Find practical answers'] as $old) {
                $this->assertStringNotContainsString($old, $page, $old);
            }
        }
        // The old positioning (a question-and-answer knowledge base with a list of platforms) is gone from all three.
        foreach ([$about, $features, $faqs] as $page) {
            foreach (['knowledge base', 'collective human experience', 'business intelligence', 'Dot.Eco-system'] as $old) {
                $this->assertStringNotContainsStringIgnoringCase($old, $page, $old);
            }
        }
    }

    public function test_a_public_page_with_no_description_of_its_own_gets_the_new_one(): void
    {
        $layout = (string) file_get_contents(resource_path('views/components/public-layout.blade.php'));

        $this->assertStringContainsString('InfoDot brings together practical business knowledge, people who share what works', $layout);
        $this->assertStringNotContainsString('follow verified solutions, and open every Dot platform', $layout);
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
            '/contact' => ['Tell us what you need.', 'privacy@infodot.co.za'],
        ];

        foreach ($pages as $path => [$heading, $text]) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, '<h1'), $path);
            $this->assertStringContainsString($heading, $html, $path);
            $this->assertStringContainsString($text, $html, $path);
            $this->assertStringContainsString('ix-header--public', $html, $path);
        }
    }

    public function test_no_public_page_names_a_person(): void
    {
        foreach (['/', '/about', '/features', '/faqs', '/complains', '/contact'] as $path) {
            // Random per-request tokens (CSP nonce, CSRF token, asset ids) are taken out first: a
            // random "…ZNbhAyi…" once failed a deploy without any page naming anyone.
            $text = preg_replace('/(nonce|data-csrf|content|value|id)="[A-Za-z0-9+\/=_-]{16,}"|\?id=[a-f0-9]+/', '', $this->get($path)->getContent());

            $this->assertDoesNotMatchRegularExpression('/\b(sakhile|bhayi)/i', (string) $text, $path);
        }
    }

    public function test_the_photographs_are_credited_on_the_about_page_and_linked_from_the_footer(): void
    {
        $about = $this->get('/about')->assertOk()->getContent();

        foreach (['Vije Vijendranath', 'Simon Hurry', 'Matti Blume', 'CC BY-SA 4.0', 'Unsplash licence'] as $credit) {
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
        $this->assertMatchesRegularExpression('/class="dsr-go" aria-label="Search questions and solutions[^"]*"><span class="dsr-arrow"/', $results, 'the top-bar search');
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

    public function test_the_visitor_tab_row_has_the_search_links_the_directory_and_the_business_tools_page_and_nothing_else(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        preg_match('/<nav class="dh-nav" aria-label="About InfoDot" data-dh-nav>(.*?)<\/nav>/s', $html, $m);

        $this->assertStringContainsString('Questions people ask', $m[1]);
        $this->assertStringContainsString('Browse public answers', $m[1]);
        // The directory is public too (R-33); without a tab the landing family of pages had no way to reach it.
        $this->assertStringContainsString('>Businesses</a>', $m[1]);
        // R-56: the Business Tools page is a public place of its own.
        $this->assertStringContainsString('>Business tools</a>', $m[1]);
        $this->assertSame(4, substr_count($m[1], '<a '), 'four places, no more');
        $this->assertStringNotContainsString('What it does', $m[1]);
        $this->assertStringNotContainsString('>About<', $m[1]);
        $this->assertStringContainsString('>About</a>', $html, 'About is still in the footer');
    }

    public function test_the_legal_pages_use_the_site_shell_with_a_contents_list_and_keep_their_words(): void
    {
        foreach (['/policy' => ['Privacy Policy', 'Who we are', 'BluePin Inc'], '/terms' => ['Terms &amp; Conditions', 'Your account', 'BluePin'], '/cookies' => ['Cookie Policy', 'Cookies we set', 'infodot_session']] as $path => [$title, $heading, $text]) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertStringContainsString('ix-header--public', $html, $path);
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

    public function test_the_landing_images_and_the_arrow_are_in_the_files_the_build_picks_up(): void
    {
        // Only public/build is copied to the document root on deploy, and the build takes images from the globs in resources/js/app.js.
        // An image kept anywhere else in public/ would be a 404 on the live site. (The committed manifest is stale by design, so the
        // source of the build is checked rather than the manifest.)
        $app = (string) file_get_contents(resource_path('js/app.js'));
        foreach (['../img/landing/*.jpg', '../img/pages/*.jpg', '../img/landing/platforms/*.png', '../img/arrow-mask.png'] as $glob) {
            $this->assertStringContainsString($glob, $app, $glob.' is not globbed into the build');
        }

        foreach (['arrow-mask.png', 'landing/market.jpg', 'landing/platforms/files.png', 'pages/jhb-skyline-1920.jpg', 'pages/rosebank-1600.jpg', 'pages/market-vegetables-1200.jpg', 'pages/market-door-1600.jpg', 'pages/bokaap-mural-1600.jpg', 'pages/jhb-city-1600.jpg', 'pages/camps-bay-cafe-1600.jpg'] as $file) {
            $this->assertFileExists(resource_path('img/'.$file));
        }
        $this->assertDirectoryDoesNotExist(public_path('img/landing'), 'landing images belong in resources/img so they ship with the build');
        $this->assertFileDoesNotExist(public_path('img/arrow-mask.png'));
    }
}
