<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * `.ix-input` is the single-line field: the design system fixes it at 36px high. Put on a
 * `<textarea>` it squeezes a multi-line box down to one line, which is how the contact, enquiry
 * and review forms shipped. Multi-line boxes take `.ix-textarea`; this keeps every view on it.
 */
class TextareaClassTest extends TestCase
{
    public function test_no_textarea_uses_the_single_line_input_class(): void
    {
        $root = dirname(__DIR__, 2).'/';
        $views = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'resources/views'));
        $found = [];

        foreach ($views as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());

            // The whole opening tag, which may run over several lines.
            preg_match_all('/<textarea\b[^>]*>/i', $source, $tags, PREG_OFFSET_CAPTURE);

            foreach ($tags[0] as [$tag, $offset]) {
                if (! preg_match('/\bclass\s*=\s*(["\'])(.*?)\1/is', $tag, $class)) {
                    continue;
                }

                if (in_array('ix-input', preg_split('/\s+/', trim($class[2])) ?: [], true)) {
                    $found[] = str_replace($root, '', $file->getPathname()).':'.(substr_count($source, "\n", 0, $offset) + 1);
                }
            }
        }

        $this->assertSame([], $found, 'Use ix-textarea, not ix-input, on these text areas: '.implode(', ', $found));
    }
}
