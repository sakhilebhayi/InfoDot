<?php

namespace Tests\Feature\Stabilisation\Support;

/**
 * The icons the site's own icon font holds (R-49). The font is Material Symbols Rounded cut down to the icons in use,
 * so an icon that is not in it would be drawn as its name. This reads the list the font was built from and finds the
 * icons a rendered page or a source file asks for, so tests can hold the two together.
 */
class IconFont
{
    /** @var list<string>|null */
    private static ?array $names = null;

    /** @return list<string> */
    public static function names(): array
    {
        return self::$names ??= array_values(array_filter(array_map('trim', file(resource_path('fonts/material-symbols-rounded.txt')) ?: [])));
    }

    /**
     * The icon names a rendered page shows: the text of every icon element, and the names an Alpine x-text may put there.
     *
     * @return list<string>
     */
    public static function shownOn(string $html): array
    {
        $shown = [];
        preg_match_all('/<(?:span|i)\b[^>]*class="[^"]*\bmaterial-symbols-rounded\b[^"]*"[^>]*>([^<]*)</', $html, $m, PREG_SET_ORDER);
        foreach ($m as [$tag, $text]) {
            if (($text = trim(html_entity_decode($text))) !== '') {
                $shown[] = $text;
            }
            if (preg_match('/x-text="([^"]*)"/', $tag, $bound)) {
                $shown = array_merge($shown, self::namesIn(html_entity_decode($bound[1], ENT_QUOTES)));
            }
        }

        return array_values(array_unique($shown));
    }

    /**
     * The icon names an expression can produce: its quoted words, leaving out the ones it only compares with
     * (=== 'pdf') or looks up by (['icon']).
     *
     * @return list<string>
     */
    public static function namesIn(string $expression): array
    {
        $expression = (string) preg_replace("/[=!]==?\\s*'[^']*'|\\[\\s*'[^']*'\\s*\\]/", '', $expression);
        preg_match_all("/'([a-z][a-z0-9_]*)'/", $expression, $quoted);

        return $quoted[1];
    }

    /** @return list<string> the icons on this page that the font does not hold */
    public static function notInTheFont(string $html): array
    {
        return array_values(array_diff(self::shownOn($html), self::names()));
    }
}
