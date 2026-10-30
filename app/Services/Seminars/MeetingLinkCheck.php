<?php

namespace App\Services\Seminars;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Is the meeting link a host typed likely to open? Checks, in order: it is an https link with a real-looking address; if it claims to be a
 * well-known meeting service its path has that service's shape; the address exists; and the page answers. Result:
 *   ok    it looks right and answered
 *   warn  nothing is wrong that we can see, but we could not confirm it (many services refuse automated checks): allowed, with a note
 *   bad   clearly broken: refused, with a reason a person can act on
 * It never connects to a private or reserved address (so it cannot be pointed at InfoDot's own network), re-checks every redirect, and its
 * answers are remembered briefly so the "test this link" button cannot be used to hammer a site.
 */
class MeetingLinkCheck
{
    /** Replaced in tests. Given a host name, returns its IP addresses. @var (callable(string): list<string>)|null */
    public static $resolver = null;

    /** @var array<string, array{name: string, host: string, path: string, example: string}> */
    private const SERVICES = [
        'meet' => ['name' => 'Google Meet', 'host' => '/^meet\.google\.com$/i', 'path' => '#^/(?:lookup/[\w-]+|[a-z]{3}-[a-z]{4}-[a-z]{3})/?$#i', 'example' => 'https://meet.google.com/abc-defg-hij'],
        'zoom' => ['name' => 'Zoom', 'host' => '/(^|\.)zoom\.us$/i', 'path' => '#^/(?:j/\d{9,11}|my/[\w.-]+|wc/(?:join/)?\d{9,11})/?$#i', 'example' => 'https://zoom.us/j/12345678901'],
        'teams' => ['name' => 'Microsoft Teams', 'host' => '/^teams\.(?:microsoft|live)\.com$/i', 'path' => '#^/(?:l/meetup-join/.+|meet/\d+)#i', 'example' => 'https://teams.microsoft.com/l/meetup-join/...'],
        'whereby' => ['name' => 'Whereby', 'host' => '/(^|\.)whereby\.com$/i', 'path' => '#^/[\w-]{3,}/?$#', 'example' => 'https://whereby.com/your-room'],
        'jitsi' => ['name' => 'Jitsi Meet', 'host' => '/^meet\.jit\.si$/i', 'path' => '#^/[\w-]{3,}/?$#', 'example' => 'https://meet.jit.si/YourRoomName'],
    ];

    /** @return array{status: string, message: string, service: ?string} */
    public function check(string $url): array
    {
        $url = trim($url);
        $shape = $this->shape($url);

        if ($shape['status'] === 'bad') {
            return $shape;
        }

        if (! config('seminars.link_check.enabled', true)) {
            return ['status' => 'warn', 'message' => 'The link looks well formed. It was not opened to check it.', 'service' => $shape['service']];
        }

        return Cache::remember('seminars:linkcheck:'.sha1($url), 600, fn () => $this->reach($url, $shape['service']));
    }

    /** @return array{status: string, message: string, service: ?string} */
    private function shape(string $url): array
    {
        $bad = fn (string $m, ?string $svc = null) => ['status' => 'bad', 'message' => $m, 'service' => $svc];
        $p = parse_url($url);

        if ($url === '' || $p === false || ($p['scheme'] ?? '') !== 'https' || empty($p['host'])) {
            return $bad('Use a full link that starts with https://');
        }

        if (isset($p['user']) || isset($p['pass'])) {
            return $bad('That link has a name or password in it. Use the plain meeting link.');
        }

        $host = strtolower($p['host']);

        if (filter_var($host, FILTER_VALIDATE_IP) || ! str_contains($host, '.') || ! preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/', $host)) {
            return $bad('That does not look like a web address. Check it for typing mistakes.');
        }

        if (isset($p['port']) && (int) $p['port'] !== 443) {
            return $bad('Meeting links do not use a custom port. Use the plain meeting link.');
        }

        foreach (self::SERVICES as $key => $svc) {
            if (preg_match($svc['host'], $host)) {
                if (! preg_match($svc['path'], $p['path'] ?? '/')) {
                    return $bad("That is not a valid {$svc['name']} link. It should look like {$svc['example']}", $key);
                }

                return ['status' => 'ok', 'message' => "A {$svc['name']} link.", 'service' => $key];
            }
        }

        return ['status' => 'ok', 'message' => 'A web link.', 'service' => null];
    }

    /** @return array{status: string, message: string, service: ?string} */
    private function reach(string $url, ?string $service): array
    {
        $name = $service ? self::SERVICES[$service]['name'] : 'The link';
        $hops = 0;
        $current = $url;

        while ($hops <= (int) config('seminars.link_check.max_redirects', 3)) {
            $host = strtolower((string) parse_url($current, PHP_URL_HOST));
            $ips = $this->resolve($host);

            if ($ips === []) {
                return ['status' => 'bad', 'message' => "The address {$host} does not exist. Check it for typing mistakes.", 'service' => $service];
            }

            if ($this->anyPrivate($ips)) {
                return ['status' => 'bad', 'message' => 'That link points to a private address, not a public meeting.', 'service' => $service];
            }

            try {
                $response = Http::timeout((int) config('seminars.link_check.timeout_seconds', 4))->withHeaders(['User-Agent' => 'InfoDot-LinkCheck/1.0'])
                    ->withOptions(['allow_redirects' => false, 'curl' => [CURLOPT_RESOLVE => [$host.':443:'.$ips[0]]]])->get($current);
            } catch (Throwable) {
                return ['status' => 'warn', 'message' => "We could not open {$host} to check it just now. If you are sure it is right you can continue.", 'service' => $service];
            }

            $code = $response->status();

            if ($code >= 300 && $code < 400 && $response->header('Location') !== '') {
                $next = $this->absolute($current, $response->header('Location'));

                // The first address passed shape(); a redirect target gets the same rules, so a redirect cannot lead to another port (curl would
                // resolve that port itself, outside the pinned address), to an IP literal, or to an address with a name and password in it.
                $q = $next === null ? false : parse_url($next);
                $odd = $q === false || isset($q['user']) || isset($q['pass']) || (isset($q['port']) && (int) $q['port'] !== 443) || filter_var($q['host'] ?? '', FILTER_VALIDATE_IP);

                if ($next === null || $odd || ! str_starts_with($next, 'https://')) {
                    return ['status' => 'warn', 'message' => "{$name} sent us somewhere we could not follow. If you are sure it is right you can continue.", 'service' => $service];
                }

                $current = $next;
                $hops++;

                continue;
            }

            return match (true) {
                $code === 404 || $code === 410 => ['status' => 'bad', 'message' => "That page does not exist (error {$code}). Check the link.", 'service' => $service],
                $code >= 500 => ['status' => 'warn', 'message' => "{$name} is not answering properly right now (error {$code}). If you are sure the link is right you can continue.", 'service' => $service],
                $code === 401 || $code === 403 || $code === 405 || $code === 429 => ['status' => 'warn', 'message' => 'The site answered but would not let us look at the page, which is normal for meeting services. We cannot confirm the link opens, so open it yourself once to be sure.', 'service' => $service],
                default => ['status' => 'ok', 'message' => "{$name} opened.", 'service' => $service],
            };
        }

        return ['status' => 'warn', 'message' => 'That link sends visitors through too many other addresses to check.', 'service' => $service];
    }

    /** @return list<string> */
    private function resolve(string $host): array
    {
        if (self::$resolver !== null) {
            return (self::$resolver)($host);
        }

        $records = @dns_get_record($host, DNS_A + DNS_AAAA) ?: [];

        return array_values(array_filter(array_map(fn ($r) => $r['ip'] ?? $r['ipv6'] ?? null, $records)));
    }

    /** @param  list<string>  $ips */
    private function anyPrivate(array $ips): bool
    {
        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return true;
            }
        }

        return false;
    }

    private function absolute(string $base, string $location): ?string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $p = parse_url($base);

        return str_starts_with($location, '/') ? 'https://'.$p['host'].$location : null;
    }
}
