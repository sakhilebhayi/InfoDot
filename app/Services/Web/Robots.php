<?php

namespace App\Services\Web;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Whether a site's robots.txt lets InfoDot's reader fetch a path (R-62 phase 3). Rules for `InfoDotBot` apply if the file has them, else the rules
 * for everyone (`*`); the longest matching rule wins and Allow beats Disallow on a tie. No robots.txt (a 4xx answer) means everything is allowed;
 * a server that cannot answer (5xx, timeout) is treated as disallowing, because the site has not said we may.
 */
class Robots
{
    public function __construct(private readonly SafeFetcher $fetcher) {}

    public function allows(string $url): bool
    {
        $parts = parse_url($url);
        if (! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        $origin = Str::lower($parts['scheme']).'://'.Str::lower($parts['host']).(isset($parts['port']) ? ':'.$parts['port'] : '');
        $path = ($parts['path'] ?? '/') === '' ? '/' : ($parts['path'] ?? '/');
        $path .= isset($parts['query']) ? '?'.$parts['query'] : '';

        $rules = Cache::remember('robots:'.sha1($origin), 3600, fn () => $this->load($origin));

        return self::decide($rules, $path);
    }

    /** @return array{allow: list<string>, disallow: list<string>, all: bool} */
    private function load(string $origin): array
    {
        try {
            $file = $this->fetcher->get($origin.'/robots.txt', ['text/plain']);
        } catch (FetchFailed $e) {
            // Nothing there (404 and the other 4xx), or an answer that is not a robots file at all (a 200 page of another kind, as a single-page
            // site often sends for any address): no rules. Anything else, such as a server error or a timeout: not allowed to read.
            return ($e->status !== null && (($e->status >= 400 && $e->status < 500) || $e->status === 200))
                ? ['allow' => [], 'disallow' => [], 'all' => false]
                : ['allow' => [], 'disallow' => [], 'all' => true];
        } catch (UnsafeUrl) {
            return ['allow' => [], 'disallow' => [], 'all' => true];
        }

        return self::parse($file['body']);
    }

    /** @return array{allow: list<string>, disallow: list<string>, all: bool} */
    public static function parse(string $text): array
    {
        $groups = [];
        $agents = [];
        $inRules = false;

        foreach (preg_split('/\R/', mb_substr($text, 0, 200000)) as $line) {
            $line = trim(preg_replace('/#.*$/', '', $line));
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode(':', $line, 2));
            $key = Str::lower($key);

            if ($key === 'user-agent') {
                if ($inRules) {
                    $agents = [];
                    $inRules = false;
                }
                $agents[] = Str::lower($value);
            } elseif (in_array($key, ['allow', 'disallow'], true)) {
                $inRules = true;
                foreach ($agents as $agent) {
                    $groups[$agent][$key][] = $value;
                }
            }
        }

        $chosen = $groups['infodotbot'] ?? $groups['*'] ?? [];

        return [
            'allow' => array_values(array_filter($chosen['allow'] ?? [], fn ($p) => $p !== '')),
            'disallow' => array_values(array_filter($chosen['disallow'] ?? [], fn ($p) => $p !== '')),
            'all' => false,
        ];
    }

    /** @param  array{allow: list<string>, disallow: list<string>, all: bool}  $rules */
    public static function decide(array $rules, string $path): bool
    {
        if ($rules['all']) {
            return false;
        }

        $best = ['len' => -1, 'allow' => true];
        foreach (['allow' => true, 'disallow' => false] as $kind => $allow) {
            foreach ($rules[$kind] as $pattern) {
                if (self::matches($pattern, $path) && (strlen($pattern) > $best['len'] || (strlen($pattern) === $best['len'] && $allow))) {
                    $best = ['len' => strlen($pattern), 'allow' => $allow];
                }
            }
        }

        return $best['allow'];
    }

    private static function matches(string $pattern, string $path): bool
    {
        $anchored = str_ends_with($pattern, '$');
        $regex = '#^'.str_replace('\*', '.*', preg_quote(rtrim($pattern, '$'), '#')).($anchored ? '$' : '').'#';

        return preg_match($regex, $path) === 1;
    }
}
