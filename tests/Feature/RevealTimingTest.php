<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * R-65: content that animates in on scroll must start arriving the moment it touches the screen, so a normal scroll never lands on blank
 * space. A one-shot reveal that waits until the element is well inside the viewport (the old 'top 90%', 'top 72%' and similar), a blur that
 * keeps a name unreadable, or a reveal observer that only fires 8% inside the screen is what hid the content.
 */
class RevealTimingTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function scripts(): array
    {
        return [
            'landing' => ['resources/js/landing.js'],
            'tools' => ['resources/js/tools.js'],
            'pages' => ['resources/js/pages.js'],
        ];
    }

    /** @dataProvider scripts */
    public function test_one_shot_reveals_start_as_the_element_reaches_the_screen(string $path): void
    {
        $source = file_get_contents(base_path($path));

        // R-68: every one-shot reveal goes through motion.js (arrive / arriveEach), so none of them can be skipped or undone.
        $this->assertMatchesRegularExpression('/\barrive(Each)?\(ScrollTrigger,/', $source, $path.' does not use the shared reveal helpers');
        $this->assertStringNotContainsString('ScrollTrigger.batch(', $source, $path.' builds its own batch reveal instead of arriveEach()');
        $this->assertDoesNotMatchRegularExpression("/start:\s*'top \d+%',\s*once:\s*true/", $source, $path.' has a one-shot reveal outside motion.js');
        $this->assertStringNotContainsString("filter: 'blur(", $source, $path.' blurs content while it arrives');
    }

    public function test_the_shared_reveal_never_hides_what_you_have_reached_and_never_undoes_itself(): void
    {
        $motion = (string) file_get_contents(resource_path('js/motion.js'));

        preg_match('/REVEAL_LINE = ([\d.]+)/', $motion, $line);
        $this->assertGreaterThanOrEqual(0.95, (float) $line[1], 'reveals start the moment the top edge is on screen');
        // A part already on screen or scrolled past is never hidden.
        $this->assertStringContainsString('!belowScreen(trigger)', $motion);
        $this->assertStringContainsString('.filter(belowScreen)', $motion);
        // Reached OR passed: a jump or a fast fling still shows it, and a sweep after every scroll catches anything left waiting.
        $this->assertStringContainsString('onEnter: go, onLeave: go, onEnterBack: go, onLeaveBack: go', $motion);
        $this->assertStringContainsString('onEnter: show, onLeave: show, onEnterBack: show, onLeaveBack: show', $motion);
        $this->assertStringContainsString("addEventListener('scrollEnd', sweep)", $motion);
        // The animated reveal keeps its own "visible"; clearing it handed the part back to a stylesheet rule that hid it again.
        preg_match_all("/clearProps: '([^']+)'/", $motion, $cleared);
        $this->assertSame(1, collect($cleared[1])->filter(fn ($c) => str_contains($c, 'opacity'))->count(), 'only the reduced-motion branch may clear opacity');
    }

    public function test_the_stylesheet_does_not_hide_scroll_content(): void
    {
        $css = (string) file_get_contents(resource_path('css/infodot.css'));

        foreach (['[data-reveal]', '[data-pg-reveal]', '.pg-prose > h2'] as $selector) {
            preg_match_all('/([^{}]*)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);
            foreach ($rules as [, $selectors, $body]) {
                if (! str_contains($selectors, $selector) || str_contains($selectors, 'motion-failsafe')) {
                    continue;
                }
                $this->assertStringNotContainsString('opacity: 0', $body, "the stylesheet hides {$selector} on its own: ".trim($selectors));
            }
        }
    }

    public function test_the_css_reveal_fires_as_soon_as_anything_is_on_screen(): void
    {
        $source = file_get_contents(base_path('resources/js/reveal.js'));

        $this->assertStringContainsString("rootMargin: '0px', threshold: 0", $source);
    }
}
