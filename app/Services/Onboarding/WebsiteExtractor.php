<?php

namespace App\Services\Onboarding;

use App\Models\TeamProfile;
use App\Services\Web\FetchFailed;
use App\Services\Web\Robots;
use App\Services\Web\SafeFetcher;
use App\Services\Web\UnsafeUrl;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Str;

/**
 * Reads what a company's own website says about it (R-62 phase 3), WITHOUT guessing. It looks only at what a site publishes for machines and what is
 * stated plainly on the page: structured data (JSON-LD), the Open Graph and description tags, the page title, mailto: and tel: links and the links to
 * its social profiles. It reads the home page and, from what the home page links to on the same site, at most one each of an About, Contact, Services
 * and Products page. Every fetch goes through SafeFetcher and respects robots.txt. A field a site does not give is simply absent: nothing is
 * inferred, no industry is guessed, and nothing is run through a language model.
 *
 * Each field carries a confidence (how direct the signal is) and the signal it came from, so the person sees what was filled in and why.
 */
class WebsiteExtractor
{
    private const SUBPAGES = ['about', 'contact', 'services', 'products'];

    private const SOCIAL = [
        'linkedin' => ['linkedin.com'],
        'facebook' => ['facebook.com', 'fb.com'],
        'instagram' => ['instagram.com'],
        'x' => ['x.com', 'twitter.com'],
        'youtube' => ['youtube.com'],
    ];

    public function __construct(private readonly SafeFetcher $fetcher, private readonly Robots $robots) {}

    /**
     * @return array{fields: array<string, array{value: mixed, confidence: float, from: string}>, pages: list<string>, host: string}
     *
     * @throws UnsafeUrl the address cannot be read at all
     * @throws FetchFailed the home page could not be read or the site asks not to be read
     */
    public function extract(string $input): array
    {
        $home = $this->homeUrl($input);
        $vetted = $this->fetcher->vet($home);
        $this->fetcher->startClock(30); // one overall time budget for every page of this import

        if (! $this->robots->allows($home)) {
            throw new FetchFailed('This site asks automated readers not to read it. You can type the details in instead.');
        }

        $page = $this->fetcher->get($home);
        $origin = $this->origin($page['url']) ?? throw new FetchFailed('That address is not a web page.');

        // The home page may have redirected to another address (www, another domain): its robots.txt counts too.
        if ($origin !== $this->origin($home) && ! $this->robots->allows($page['url'])) {
            throw new FetchFailed('This site asks automated readers not to read it. You can type the details in instead.');
        }
        $candidates = $this->signals($page['body'], $page['url'], $origin);
        $pages = [$page['url']];

        foreach ($this->subpageUrls($page['body'], $page['url'], $origin) as $sub) {
            if (count($pages) >= 5 || ! $this->robots->allows($sub)) {
                continue;
            }

            try {
                $fetched = $this->fetcher->get($sub);
            } catch (FetchFailed|UnsafeUrl) {
                continue; // a page that cannot be read costs us only that page
            }

            $pages[] = $fetched['url'];
            $candidates = array_merge($candidates, $this->signals($fetched['body'], $fetched['url'], $origin));
        }

        return ['fields' => $this->best($candidates, $origin), 'pages' => $pages, 'host' => $vetted['host']];
    }

    /** The address as a person typed it, made a full address: https unless they wrote http. */
    private function homeUrl(string $input): string
    {
        $input = trim($input);
        $url = str_contains($input, '://') ? $input : 'https://'.$input;

        // A bare host is the home page: ask for its root path explicitly.
        return parse_url($url, PHP_URL_PATH) === null && ! str_contains($url, '?') ? $url.'/' : $url;
    }

    /** The scheme, host and port of an address, or null when it does not have them (a link such as `http:///` or `http://?a`). */
    private function origin(string $url): ?string
    {
        $p = parse_url($url);

        return is_array($p) && ! empty($p['scheme']) && ! empty($p['host'])
            ? Str::lower($p['scheme']).'://'.Str::lower($p['host']).(isset($p['port']) ? ':'.$p['port'] : '')
            : null;
    }

    private function dom(string $html): ?DOMDocument
    {
        if (trim($html) === '') {
            return null;
        }

        $html = mb_convert_encoding($html, 'UTF-8', mb_detect_encoding($html, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true) ?: 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $dom = new DOMDocument;
        // No entity substitution and no network access while parsing.
        $ok = $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $ok ? $dom : null;
    }

    /** @return list<string> */
    private function subpageUrls(string $html, string $pageUrl, string $origin): array
    {
        $dom = $this->dom($html);
        if ($dom === null) {
            return [];
        }

        $found = [];
        foreach ((new DOMXPath($dom))->query('//a[@href]') as $a) {
            if (! $a instanceof DOMElement) {
                continue;
            }
            $abs = $this->resolveHref($pageUrl, $a->getAttribute('href'));
            if ($abs === null || $this->origin($abs) !== $origin) {
                continue;
            }

            $path = Str::lower(parse_url($abs, PHP_URL_PATH) ?: '/');
            foreach (self::SUBPAGES as $word) {
                if (! isset($found[$word]) && preg_match('#(^|/)'.$word.'([-_/.]|$)|(^|/)[a-z-]*'.$word.'(-us)?(/|\.|$)#', $path) === 1 && $path !== '/') {
                    $found[$word] = strtok($abs, '#');
                }
            }
        }

        return array_values($found);
    }

    private function resolveHref(string $base, string $href): ?string
    {
        $href = trim($href);
        if ($href === '' || preg_match('#^(mailto:|tel:|javascript:|data:|\#)#i', $href) === 1) {
            return null;
        }

        $b = parse_url($base);
        $origin = $b['scheme'].'://'.$b['host'].(isset($b['port']) ? ':'.$b['port'] : '');

        if (preg_match('#^https?://#i', $href) === 1) {
            return $href;
        }
        if (str_starts_with($href, '//')) {
            return $b['scheme'].':'.$href;
        }
        if (str_starts_with($href, '/')) {
            return $origin.$href;
        }

        return $origin.rtrim(Str::beforeLast($b['path'] ?? '/', '/'), '/').'/'.$href;
    }

    /**
     * Everything one page says, as candidates: [field, value, confidence, from].
     *
     * @return list<array{0: string, 1: mixed, 2: float, 3: string}>
     */
    private function signals(string $html, string $pageUrl, string $origin): array
    {
        $dom = $this->dom($html);
        if ($dom === null) {
            return [];
        }

        $xp = new DOMXPath($dom);
        $out = [];

        foreach ($this->jsonLd($xp) as $org) {
            $this->fromOrganization($org, $out);
        }

        $meta = function (string $attr, string $name) use ($xp): ?string {
            $node = $xp->query('//meta[translate(@'.$attr.', "ABCDEFGHIJKLMNOPQRSTUVWXYZ", "abcdefghijklmnopqrstuvwxyz")="'.$name.'"]')->item(0);

            return $node instanceof DOMElement ? $this->clean($node->getAttribute('content')) : null;
        };

        if ($v = $meta('property', 'og:site_name')) {
            $out[] = ['name', $v, 0.6, 'Open Graph site name'];
        }
        if ($v = $meta('property', 'og:description')) {
            $out[] = ['description', $v, 0.55, 'Open Graph description'];
        }
        if ($v = $meta('name', 'description')) {
            $out[] = ['description', $v, 0.5, 'page description'];
        }
        $title = $xp->query('//title')->item(0);
        if ($title && ($t = $this->clean($title->textContent))) {
            $out[] = ['name', trim(preg_split('/\s[|\x{2013}\x{2014}-]\s/u', $t)[0]), 0.3, 'page title'];
        }

        foreach ($xp->query('//a[@href]') as $a) {
            if (! $a instanceof DOMElement) {
                continue;
            }
            $href = trim($a->getAttribute('href'));

            if (preg_match('/^mailto:/i', $href) === 1) {
                $email = Str::lower(trim(Str::before(substr($href, 7), '?')));
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $out[] = ['contact_email', $email, 0.7, 'a mailto link'];
                }
            } elseif (preg_match('/^tel:/i', $href) === 1) {
                if ($phone = $this->phone(rawurldecode(substr($href, 4)))) {
                    $out[] = ['contact_phone', $phone, 0.7, 'a tel link'];
                }
            } elseif (($social = $this->social($href)) !== null) {
                $out[] = ['links', $social, 0.7, 'a social profile link'];
            }
        }

        return $out;
    }

    /** @return list<array<string, mixed>> the Organization-like objects in the page's JSON-LD */
    private function jsonLd(DOMXPath $xp): array
    {
        $orgs = [];

        foreach ($xp->query('//script[@type="application/ld+json"]') as $script) {
            $data = json_decode(trim($script->textContent), true);
            if (! is_array($data)) {
                continue;
            }

            $stack = array_is_list($data) ? $data : [$data];
            while ($stack !== []) {
                $item = array_shift($stack);
                if (! is_array($item)) {
                    continue;
                }
                foreach (['@graph', 'mainEntity'] as $key) {
                    if (isset($item[$key]) && is_array($item[$key])) {
                        array_push($stack, ...(array_is_list($item[$key]) ? $item[$key] : [$item[$key]]));
                    }
                }
                $types = array_map('strval', (array) ($item['@type'] ?? []));
                foreach ($types as $type) {
                    if (preg_match('/Organization|Business|Corporation|Store|Restaurant|Service|Company/i', $type) === 1 && ! in_array($type, ['WebSite', 'WebPage'], true)) {
                        $orgs[] = $item;
                        break;
                    }
                }
            }
        }

        return $orgs;
    }

    /**
     * @param  array<string, mixed>  $org
     * @param  list<array{0: string, 1: mixed, 2: float, 3: string}>  $out
     */
    private function fromOrganization(array $org, array &$out): void
    {
        $text = fn ($v) => is_string($v) ? $this->clean($v) : null;

        if ($v = $text($org['name'] ?? null)) {
            $out[] = ['name', $v, 0.9, 'structured data'];
        }
        if ($v = $text($org['description'] ?? null)) {
            $out[] = ['description', $v, 0.85, 'structured data'];
        }
        if (($v = $text($org['email'] ?? null)) && filter_var(Str::lower(preg_replace('/^mailto:/i', '', $v)), FILTER_VALIDATE_EMAIL)) {
            $out[] = ['contact_email', Str::lower(preg_replace('/^mailto:/i', '', $v)), 0.85, 'structured data'];
        }
        if (($v = $text($org['telephone'] ?? null)) && ($phone = $this->phone($v))) {
            $out[] = ['contact_phone', $phone, 0.85, 'structured data'];
        }

        $address = $org['address'] ?? null;
        if (is_array($address)) {
            $country = $address['addressCountry'] ?? null;
            $parts = array_filter([$address['streetAddress'] ?? null, $address['addressLocality'] ?? null, $address['addressRegion'] ?? null, $address['postalCode'] ?? null, is_array($country) ? ($country['name'] ?? null) : $country], fn ($p) => is_string($p) && trim($p) !== '');
            if ($parts !== []) {
                $out[] = ['location', $this->clean(implode(', ', $parts)), 0.8, 'structured data'];
            }
        } elseif (is_string($address) && $this->clean($address) !== '') {
            $out[] = ['location', $this->clean($address), 0.7, 'structured data'];
        }

        foreach ((array) ($org['sameAs'] ?? []) as $profile) {
            if (is_string($profile) && ($social = $this->social($profile)) !== null) {
                $out[] = ['links', $social, 0.85, 'structured data'];
            }
        }
    }

    /** @return array{0: string, 1: string}|null a social slot and its profile address, for an https profile link (not a share button) */
    private function social(string $href): ?array
    {
        $parts = parse_url(trim($href));
        if (! is_array($parts) || Str::lower($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user'])) {
            return null;
        }

        $host = Str::lower(preg_replace('/^(www|m|mobile)\./i', '', $parts['host']));
        $path = $parts['path'] ?? '/';
        if (preg_match('#/(share|sharer|intent|dialog|plugins|hashtag)(/|\.php|$)#i', $path) === 1 || trim($path, '/') === '') {
            return null;
        }

        foreach (self::SOCIAL as $slot => $hosts) {
            if (in_array($host, $hosts, true) && in_array($slot, TeamProfile::LINK_KEYS, true)) {
                return [$slot, 'https://'.$parts['host'].rtrim($path, '/')];
            }
        }

        return null;
    }

    private function phone(string $raw): ?string
    {
        $raw = trim($raw);
        $digits = preg_replace('/\D/', '', $raw);

        if (strlen($digits) < 7 || strlen($digits) > 15) {
            return null;
        }

        return mb_substr(preg_replace('/[^0-9+() \-]/', '', $raw), 0, 40);
    }

    private function clean(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', strip_tags(html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8'))));
    }

    /**
     * The best candidate per field. Social links are collected per slot. Values are cut to the sizes the profile holds.
     *
     * @param  list<array{0: string, 1: mixed, 2: float, 3: string}>  $candidates
     * @return array<string, array{value: mixed, confidence: float, from: string}>
     */
    private function best(array $candidates, string $origin): array
    {
        $best = [];
        $links = [];

        foreach ($candidates as [$field, $value, $confidence, $from]) {
            if ($field === 'links') {
                [$slot, $url] = $value;
                if (! isset($links[$slot]) || $confidence > $links[$slot]['confidence']) {
                    $links[$slot] = ['value' => mb_substr($url, 0, 255), 'confidence' => $confidence, 'from' => $from];
                }

                continue;
            }
            if (! isset($best[$field]) || $confidence > $best[$field]['confidence']) {
                $best[$field] = ['value' => $value, 'confidence' => $confidence, 'from' => $from];
            }
        }

        $limits = ['name' => 255, 'description' => 600, 'contact_email' => 255, 'contact_phone' => 40, 'location' => 160];
        foreach ($limits as $field => $max) {
            if (isset($best[$field])) {
                $best[$field]['value'] = Str::limit((string) $best[$field]['value'], $max, '');
            }
        }

        $fields = $best;
        $fields['website'] = ['value' => $origin, 'confidence' => 1.0, 'from' => 'the address you gave'];
        if ($links !== []) {
            $fields['links'] = [
                'value' => array_map(fn ($l) => $l['value'], $links),
                'confidence' => round(array_sum(array_column($links, 'confidence')) / count($links), 2),
                'from' => 'social profile links',
            ];
        }

        return $fields;
    }
}
