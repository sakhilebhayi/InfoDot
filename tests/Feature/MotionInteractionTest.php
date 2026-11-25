<?php

namespace Tests\Feature;

use Tests\TestCase;

/** R-64 phase 9: motion that earns its place. Only cheap properties move, hover is for a mouse, nothing is hidden without the script, and reduced motion wins. */
class MotionInteractionTest extends TestCase
{
    private function css(): string
    {
        return (string) file_get_contents(resource_path('css/infodot.css'));
    }

    private function block(): string
    {
        $css = $this->css();
        $a = strpos($css, 'motion and interaction (R-64 phase 9)');
        $b = strpos($css, '/* ---------------------------------------------------------------- menus */', $a);

        return substr($css, (int) $a, (int) $b - (int) $a);
    }

    public function test_the_new_motion_moves_only_transform_and_opacity(): void
    {
        $block = $this->block();

        preg_match_all('/transition(?:-property)?:\s*([^;]+);/', $block, $m);
        foreach ($m[1] as $value) {
            $this->assertDoesNotMatchRegularExpression('/\b(width|height|top|left|right|bottom|margin|padding|font-size|inline-size)\b/', preg_replace('/var\([^)]*\)/', '', $value), "transition moves a layout property: $value");
        }

        $this->assertStringNotContainsString('@keyframes ix-read {\n    to {\n        width', $block);
        $this->assertStringContainsString('transform: scaleX(1)', $block, 'the reading bar grows by transform');
    }

    public function test_hover_lift_is_for_a_mouse_only(): void
    {
        $block = $this->block();

        $this->assertStringContainsString('@media (hover: hover) and (pointer: fine)', $block);
        $hover = substr($block, (int) strpos($block, '@media (hover: hover)'), 700);
        $this->assertStringContainsString('.ix-bizcard:hover', $hover);
        $this->assertSame(0, preg_match('/^\s*\.ix-bizcard:hover[^{]*\{[^}]*translateY/m', substr($block, 0, (int) strpos($block, '@media (hover: hover)'))), 'no lift outside the hover media query');
    }

    public function test_every_duration_in_the_new_block_is_a_token(): void
    {
        $this->assertDoesNotMatchRegularExpression('/(?<![\w.-])\d*\.?\d+m?s(?![\w-])/', preg_replace(['~/\*.*?\*/~s', '/--ix-[a-z-]+:\s*[^;]+;/'], '', $this->block()), 'a raw duration in the motion block');

        $css = $this->css();
        $this->assertStringContainsString('animation-duration: var(--ix-dur-slow)', $css, 'the page transition uses the tokens');
        $this->assertStringContainsString('--ix-stagger: 60ms', $css);
        $this->assertDoesNotMatchRegularExpression('/::view-transition-[a-z(-]+[^{]*\{[^}]*animation-duration:\s*\d/', $css, 'no raw duration on the page transition');
    }

    public function test_nothing_is_hidden_unless_the_script_has_run_and_reduced_motion_is_respected(): void
    {
        $block = $this->block();
        $this->assertStringContainsString('.ix-reveal-ready [data-ix-reveal]:not(.is-in)', $block, 'hidden only under the class the script adds');
        $this->assertDoesNotMatchRegularExpression('/^\s*\[data-ix-reveal\][^{]*\{[^}]*opacity:\s*0/m', $block);

        $js = (string) file_get_contents(resource_path('js/reveal.js'));
        $this->assertStringContainsString('prefers-reduced-motion: reduce', $js);
        $this->assertStringContainsString("'IntersectionObserver' in window", $js);
        $this->assertStringContainsString('failsafe', $js, 'a slow device shows everything instead of a half-drawn page');
        $this->assertStringNotContainsString("addEventListener('scroll'", $js, 'no scroll handler: the browser says when something enters');
        $this->assertStringNotContainsString('requestAnimationFrame', $js);

        $this->assertStringContainsString('prefers-reduced-motion: no-preference', substr($block, (int) strpos($block, '.ix-readbar {')), 'the reading bar is off under reduced motion');
    }

    public function test_the_reading_bar_is_scroll_linked_in_css_with_no_script_and_is_decorative(): void
    {
        $block = $this->block();

        $this->assertStringContainsString('animation-timeline: scroll(root block)', $block);
        $this->assertStringContainsString('@supports (animation-timeline: scroll())', $block);
        $this->assertStringContainsString('pointer-events: none', $block);

        foreach (['components/legal-page', 'solutions/view'] as $view) {
            $this->assertStringContainsString('class="ix-readbar" aria-hidden="true"', (string) file_get_contents(resource_path("views/$view.blade.php")));
        }
    }

    public function test_the_pages_that_reveal_are_marked_and_the_marks_are_few_and_plain(): void
    {
        foreach (['home', 'businesses/_card'] as $view) {
            $this->assertStringContainsString('data-ix-reveal', (string) file_get_contents(resource_path("views/$view.blade.php")), $view);
        }

        $count = 0;
        foreach (['home', 'businesses/_card'] as $view) {
            $count += substr_count((string) file_get_contents(resource_path("views/$view.blade.php")), 'data-ix-reveal');
        }
        $this->assertLessThanOrEqual(6, $count, 'motion is used selectively');
    }

    public function test_expanding_a_details_is_a_progressive_enhancement(): void
    {
        $this->assertStringContainsString('@supports selector(::details-content)', $this->block());
        $this->assertStringContainsString('interpolate-size: allow-keywords', $this->block());
    }
}
