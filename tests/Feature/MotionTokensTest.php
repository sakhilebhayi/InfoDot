<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * R-64 phase 2: the motion system is defined once. The durations and curves live in the stylesheet's custom properties, the GSAP module mirrors the same
 * numbers, and nobody declares a raw duration in a transition or animation again.
 */
class MotionTokensTest extends TestCase
{
    private function css(): string
    {
        return (string) file_get_contents(resource_path('css/infodot.css'));
    }

    /** @return array<string, string> */
    private function tokens(): array
    {
        preg_match_all('/--ix-(dur-[a-z-]+|rise|ease-out|ease-in-out|field-h(?:-sm)?)\s*:\s*([^;]+);/', $this->css(), $m, PREG_SET_ORDER);

        return collect($m)->mapWithKeys(fn ($x) => [$x[1] => trim($x[2])])->all();
    }

    public function test_the_tokens_exist(): void
    {
        $t = $this->tokens();

        foreach (['dur-fast', 'dur-base', 'dur-slow', 'dur-scene', 'dur-scene-slow', 'rise', 'ease-out', 'ease-in-out', 'field-h', 'field-h-sm'] as $name) {
            $this->assertArrayHasKey($name, $t, "--ix-$name is defined");
        }
        $this->assertSame('120ms', $t['dur-fast']);
        $this->assertStringContainsString('(pointer: coarse)', $this->css(), 'controls are thumb-sized on touch screens');
    }

    public function test_the_gsap_module_mirrors_the_css_tokens(): void
    {
        $t = $this->tokens();
        $js = (string) file_get_contents(resource_path('js/motion.js'));

        preg_match('/DUR = \{([^}]+)\}/', $js, $dur);
        preg_match_all('/(\w+):\s*([\d.]+)/', $dur[1], $pairs, PREG_SET_ORDER);
        $seconds = collect($pairs)->mapWithKeys(fn ($p) => [$p[1] => (float) $p[2]])->all();

        $ms = fn (string $v) => (float) rtrim($v, 'ms');
        $this->assertEqualsWithDelta($ms($t['dur-fast']) / 1000, $seconds['fast'], 0.0001);
        $this->assertEqualsWithDelta($ms($t['dur-base']) / 1000, $seconds['base'], 0.0001);
        $this->assertEqualsWithDelta($ms($t['dur-slow']) / 1000, $seconds['slow'], 0.0001);
        $this->assertEqualsWithDelta($ms($t['dur-scene']) / 1000, $seconds['scene'], 0.0001);
        $this->assertEqualsWithDelta($ms($t['dur-scene-slow']) / 1000, $seconds['sceneSlow'], 0.0001);

        preg_match('/RISE = (\d+)/', $js, $rise);
        $this->assertSame((int) rtrim($t['rise'], 'px'), (int) $rise[1]);
    }

    public function test_no_stylesheet_declares_a_raw_duration_in_a_transition_or_animation(): void
    {
        foreach (['infodot.css', 'shell.css', 'app.css'] as $file) {
            $css = (string) file_get_contents(resource_path('css/'.$file));
            preg_match_all('/(?<![\w-])(?:transition|animation):[^;{}]*;/', $css, $m);

            foreach ($m[0] as $declaration) {
                if (str_contains($declaration, 'infinite')) {
                    continue; // a slow ambient loop (the tools page drift) is not a state change
                }
                $this->assertDoesNotMatchRegularExpression('/(?<![\w.-])\d*\.?\d+m?s(?![\w-])/', $declaration, "$file: $declaration");
            }
        }
    }

    public function test_reduced_motion_is_still_handled_once_and_for_everything(): void
    {
        $app = (string) file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('@media (prefers-reduced-motion: reduce)', $app);
        $this->assertStringContainsString('transition-duration: 0.01ms !important', $app);
        $this->assertStringContainsString('animation-duration: 0.01ms !important', $app);

        $js = (string) file_get_contents(resource_path('js/motion.js'));
        $this->assertStringContainsString('reducedMotion()', $js);
        $this->assertStringContainsString("clearProps: 'opacity,visibility,transform'", $js, 'content is shown at once, never left hidden');
    }
}
