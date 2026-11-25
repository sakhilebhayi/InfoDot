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

        preg_match_all("/start:\s*'top (\d+)%',\s*once:\s*true/", $source, $inline);
        preg_match_all("/batch\([^{]+\{\s*start:\s*'top (\d+)%'/", $source, $batched);
        $starts = array_merge($inline[1], $batched[1]);

        $this->assertNotEmpty($starts, $path.' has no one-shot reveals to check');
        foreach ($starts as $percent) {
            $this->assertGreaterThanOrEqual(95, (int) $percent, $path.": a reveal waits until 'top {$percent}%'");
        }
        $this->assertStringNotContainsString("filter: 'blur(", $source, $path.' blurs content while it arrives');
    }

    public function test_the_css_reveal_fires_as_soon_as_anything_is_on_screen(): void
    {
        $source = file_get_contents(base_path('resources/js/reveal.js'));

        $this->assertStringContainsString("rootMargin: '0px', threshold: 0", $source);
    }
}
