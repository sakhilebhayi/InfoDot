<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * R-64 phase 11: every form control in the views wears the shared classes, so no screen drifts back to the browser's look or to one-off sizing. The checks
 * read the Blade source: a select is `ix-select`, a text-like input `ix-input`, a textarea `ix-textarea`, none carries Tailwind sizing or an inline style,
 * and every label is tied to a control (by `for` or by wrapping it).
 */
class FormHygieneTest extends TestCase
{
    /** Files whose controls are styled by their own component (the header search, the hero search, the shared form components, Jetstream's wrapper). */
    private const OWN_STYLE = ['components/', 'vendor/jetstream/', 'partials/home-hero', 'livewire/dot-switcher', 'solutions/index', 'questions/index'];

    private const INPUT_TYPES = ['text', 'number', 'email', 'search', 'url', 'tel', 'password', 'date', 'time', 'datetime-local', 'month', 'week'];

    private const TAILWIND = '/\b(block|w-full|w-\d+|h-\d+|mt-\d+|mb-\d+|mx-\d+|px-\d+|py-\d+|rounded\S*|shadow\S*|focus:\S+|disabled:\S+)\b/';

    /** @return list<array{file: string, line: int, tag: string, attrs: string}> */
    private function controls(): array
    {
        $out = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'))) as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }
            $rel = str_replace(resource_path('views').'/', '', $file->getPathname());
            $source = (string) file_get_contents($file->getPathname());

            if (preg_match('~^('.implode('|', array_map(fn ($p) => preg_quote($p, '~'), self::OWN_STYLE)).')~', $rel) === 1) {
                continue;
            }

            preg_match_all('/<(select|textarea|input)\b((?:->|=>|[^>])*?)\/?>/s', $source, $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
            foreach ($m as $match) {
                $out[] = ['file' => $rel, 'line' => substr_count($source, "\n", 0, $match[0][1]) + 1, 'tag' => $match[1][0], 'attrs' => $match[2][0]];
            }
        }

        return $out;
    }

    private function classOf(string $attrs): string
    {
        return preg_match('/\bclass="([^"]*)"/', $attrs, $c) === 1 ? $c[1] : '';
    }

    public function test_every_select_textarea_and_text_input_wears_the_shared_class(): void
    {
        $problems = [];

        foreach ($this->controls() as $c) {
            $class = $this->classOf($c['attrs']);

            if (str_contains($c['attrs'], 'tabindex="-1"') || str_contains($class, '{{') || str_contains($class, '@')) {
                continue; // a honeypot, or a class chosen by the page at run time
            }

            if ($c['tag'] === 'select' && ! str_contains($class, 'ix-select')) {
                $problems[] = "{$c['file']}:{$c['line']} select without ix-select";
            }
            if ($c['tag'] === 'textarea' && ! str_contains($class, 'ix-textarea')) {
                $problems[] = "{$c['file']}:{$c['line']} textarea without ix-textarea";
            }
            if ($c['tag'] === 'input') {
                $type = preg_match('/type=["\']([^"\']+)/', $c['attrs'], $t) === 1 ? strtolower($t[1]) : 'text';
                if (in_array($type, self::INPUT_TYPES, true) && ! str_contains($class, 'ix-input') && ! str_contains($class, 'ix-askbar__input') && ! str_contains($class, 'lp-search__input')) {
                    $problems[] = "{$c['file']}:{$c['line']} {$type} input without ix-input";
                }
            }
        }

        $this->assertSame([], $problems, implode("\n", $problems));
    }

    public function test_no_control_carries_tailwind_sizing_or_an_inline_style(): void
    {
        $problems = [];

        foreach ($this->controls() as $c) {
            $class = preg_replace('/\{\{.*?\}\}/s', '', $this->classOf($c['attrs']));

            if ($class !== '' && preg_match(self::TAILWIND, (string) $class) === 1) {
                $problems[] = "{$c['file']}:{$c['line']} Tailwind classes on a control: $class";
            }
            if (preg_match('/\sstyle="/', $c['attrs']) === 1) {
                $problems[] = "{$c['file']}:{$c['line']} inline style on a control";
            }
        }

        $this->assertSame([], $problems, implode("\n", $problems));
    }

    public function test_every_label_is_tied_to_a_control(): void
    {
        $problems = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views'))) as $file) {
            if (! $file->isFile() || ! str_ends_with($file->getFilename(), '.blade.php') || str_contains($file->getPathname(), '/vendor/jetstream/')) {
                continue;
            }
            $source = (string) file_get_contents($file->getPathname());
            preg_match_all('/<label\b((?:->|=>|[^>])*?)>(.*?)<\/label>/s', $source, $m, PREG_SET_ORDER);

            foreach ($m as $label) {
                if (preg_match('/(?:^|\s|:)for=/', $label[1]) !== 1 && preg_match('/<(input|select|textarea|x-form\.|x-jet-input|x-jet-checkbox)/', $label[2]) !== 1) {
                    $problems[] = str_replace(resource_path('views').'/', '', $file->getPathname()).': label '.trim(preg_replace('/\s+/', ' ', strip_tags($label[2])));
                }
            }
        }

        $this->assertSame([], $problems, implode("\n", $problems));
    }

    public function test_the_modifier_classes_that_replaced_inline_sizes_exist(): void
    {
        $css = (string) file_get_contents(resource_path('css/infodot.css'));

        foreach (['.ix-input--narrow', '.ix-input--tiny', '.ix-textarea--short', '.ix-topicbar .ix-select', '.ix-dir__filter .ix-select'] as $needle) {
            $this->assertStringContainsString($needle, $css, $needle);
        }
    }
}
