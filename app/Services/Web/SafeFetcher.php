<?php

namespace App\Services\Web;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Psr\Http\Message\StreamInterface;
use Throwable;

/**
 * Fetches one public web page for InfoDot (R-62 phase 3), and refuses anything that could be used to reach InfoDot's own network (server-side
 * request forgery). It is the only way InfoDot reads a web address that a person typed.
 *
 * Every address, and every redirect it follows, is checked again from scratch:
 *  - http or https only, no user name or password in the address, port 80 or 443 only;
 *  - a real host name (a dot in it, not localhost or a private-network suffix) or an address literal, never an unusual spelling;
 *  - EVERY address the name resolves to must be a public one. A name that resolves to even one private, loopback, link-local, carrier-grade NAT,
 *    multicast, documentation or otherwise reserved address (IPv4 or IPv6, including IPv4-mapped and translation prefixes) is refused;
 *  - the connection is pinned to the address that was checked, so a second look-up cannot send it somewhere else (DNS rebinding);
 *  - at most `maxRedirects` redirects, each one vetted like the first; the connection and the whole request have short time limits;
 *  - only a 200 answer of an allowed content type, and no more than `maxBytes` of it, is read.
 */
class SafeFetcher
{
    public const USER_AGENT = 'InfoDotBot/1.0 (+https://infodot.co.za; reads a company web page when its owner asks for an import)';

    /** Replaced in tests. Given a host name, returns its IP addresses. @var (callable(string): list<string>)|null */
    public static $resolver = null;

    private ?float $deadlineAt = null;

    public function __construct(
        private readonly int $maxBytes = 2097152,
        private readonly int $maxRedirects = 3,
        private readonly int $timeout = 10,
        private readonly int $connectTimeout = 5,
    ) {}

    /**
     * @param  list<string>  $accept  content types that may be read (the part before any `;`)
     * @return array{url: string, status: int, type: string, body: string}
     *
     * @throws UnsafeUrl
     * @throws FetchFailed
     */
    public function get(string $url, array $accept = ['text/html', 'application/xhtml+xml']): array
    {
        $current = $url;
        $previousScheme = null;

        for ($hop = 0; $hop <= $this->maxRedirects; $hop++) {
            $vetted = $this->vet($current);

            if ($previousScheme === 'https' && $vetted['scheme'] === 'http') {
                throw new FetchFailed('The site redirected from a secure page to an unencrypted one.');
            }
            $previousScheme = $vetted['scheme'];

            // The address that was checked is the address that is requested: the URL is rebuilt from its checked parts (so a trailing dot or
            // other spelling cannot make cURL look the name up again), and cURL is told which IP to use for it.
            $request = $vetted['url'];
            $buffer = '';
            $tooLarge = false;
            $max = $this->maxBytes;
            $options = $this->requestOptions($vetted['host'], $vetted['port'], $vetted['ip'], $this->remaining());
            $options['curl'][CURLOPT_WRITEFUNCTION] = function ($handle, string $chunk) use (&$buffer, &$tooLarge, $max): int {
                $buffer .= $chunk;
                if (strlen($buffer) > $max) {
                    $tooLarge = true;

                    return 0; // abort the transfer: nothing more is read
                }

                return strlen($chunk);
            };

            try {
                $response = Http::withHeaders(['User-Agent' => self::USER_AGENT, 'Accept' => implode(',', $accept)])->withOptions($options)->get($request);
            } catch (Throwable $e) {
                throw new FetchFailed($tooLarge ? 'That page is too large to read.' : 'The site did not answer in time.');
            }

            $status = $response->status();

            if (in_array($status, [301, 302, 303, 307, 308], true)) {
                $location = trim($response->header('Location'));
                if ($location === '') {
                    throw new FetchFailed('The site sent a redirect with nowhere to go.', $status);
                }
                $current = $this->absolute($request, $location);

                continue;
            }

            if ($status !== 200) {
                throw new FetchFailed('The site answered with an error ('.$status.').', $status);
            }

            $type = Str::lower(trim(Str::before($response->header('Content-Type'), ';')));
            if (! in_array($type, $accept, true)) {
                throw new FetchFailed('That address is not a web page.', $status);
            }

            // A real transfer arrives through the capped write function above; a body that did not (an in-memory response) is read with the same cap.
            $body = $buffer !== '' ? $buffer : $this->read($response->toPsrResponse()->getBody());

            return ['url' => $request, 'status' => $status, 'type' => $type, 'body' => $body];
        }

        throw new FetchFailed('The site redirected too many times.');
    }

    /**
     * The HTTP client options for one checked address. There is deliberately NO `stream` option (that makes Guzzle use PHP's stream handler, which
     * ignores CURLOPT_RESOLVE and so would not pin the connection), and no proxy (an environment proxy would resolve the name itself).
     *
     * @return array<string, mixed>
     */
    public function requestOptions(string $host, int $port, string $ip, int $timeout): array
    {
        return [
            'allow_redirects' => false,
            'timeout' => $timeout,
            'connect_timeout' => min($this->connectTimeout, $timeout),
            'proxy' => '',
            'curl' => [
                CURLOPT_RESOLVE => [$host.':'.$port.':'.$ip],
                CURLOPT_PROXY => '',
                CURLOPT_FOLLOWLOCATION => false,
                // A server that trickles bytes to hold the request open is cut off: under 1 KB a second for 5 seconds.
                CURLOPT_LOW_SPEED_LIMIT => 1024,
                CURLOPT_LOW_SPEED_TIME => 5,
            ],
        ];
    }

    /** Starts one overall time budget shared by every fetch that follows (the home page, robots.txt, the other pages). */
    public function startClock(int $seconds): void
    {
        $this->deadlineAt = microtime(true) + $seconds;
    }

    /** The seconds one request may take: the per-request limit, cut down to what is left of the shared budget. @throws FetchFailed */
    private function remaining(): int
    {
        if ($this->deadlineAt === null) {
            return $this->timeout;
        }

        $left = (int) ceil($this->deadlineAt - microtime(true));
        if ($left < 1) {
            throw new FetchFailed('Reading the site took too long.');
        }

        return min($this->timeout, $left);
    }

    /**
     * Checks one address and returns where to connect.
     *
     * @return array{host: string, port: int, scheme: string, ip: string, url: string}
     *
     * @throws UnsafeUrl
     */
    public function vet(string $url): array
    {
        $url = trim($url);
        $parts = strlen($url) <= 2048 ? parse_url($url) : false;

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new UnsafeUrl('That is not a web address.');
        }

        $scheme = Str::lower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new UnsafeUrl('Only http and https addresses can be read.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new UnsafeUrl('An address with a name or password in it cannot be read.');
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        if (! in_array($port, [80, 443], true)) {
            throw new UnsafeUrl('Only the usual web ports can be read.');
        }

        $host = $this->normaliseHost($parts['host']);

        $ips = filter_var($host, FILTER_VALIDATE_IP) !== false ? [$host] : $this->resolve($host);
        if ($ips === []) {
            throw new UnsafeUrl('We could not find that site.');
        }
        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                throw new UnsafeUrl('That address points inside a private or reserved network.');
            }
        }

        // Prefer an IPv4 address when the name has both.
        $chosen = collect($ips)->first(fn ($ip) => filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) ?? $ips[0];

        // The address to request, rebuilt from the checked parts: no user info, no fragment, the host as normalised (no trailing dot).
        $hostPart = str_contains($host, ':') ? '['.$host.']' : $host;
        $path = ($parts['path'] ?? '') === '' ? '/' : $parts['path'];
        $rebuilt = $scheme.'://'.$hostPart.(isset($parts['port']) ? ':'.$parts['port'] : '').$path.(isset($parts['query']) ? '?'.$parts['query'] : '');

        return ['host' => $host, 'port' => $port, 'scheme' => $scheme, 'ip' => $chosen, 'url' => $rebuilt];
    }

    /** True only for an address on the public internet. Anything that cannot be read as an IP address is not public. */
    public static function isPublicIp(string $ip): bool
    {
        $packed = @inet_pton($ip);
        if ($packed === false) {
            return false;
        }

        if (strlen($packed) === 4) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }

            foreach (['100.64.0.0/10', '192.0.0.0/24', '192.0.2.0/24', '192.88.99.0/24', '198.18.0.0/15', '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/4'] as $range) {
                if (self::inCidr($packed, $range)) {
                    return false;
                }
            }

            return true;
        }

        // An IPv4 address written as IPv6 is judged as the IPv4 address it carries.
        if (self::inCidr($packed, '::ffff:0:0/96')) {
            return self::isPublicIp(inet_ntop(substr($packed, 12)));
        }

        // IPv6: only global unicast (2000::/3) can be public, less the blocks inside it that are not (IETF protocol assignments including Teredo and
        // the ORCHID ranges, documentation, 6to4). Everything else (loopback, unique-local, link-local, site-local, multicast, translation prefixes,
        // IPv4-compatible and IPv4-translated forms, and anything not yet allocated) is refused.
        if (! self::inCidr($packed, '2000::/3')) {
            return false;
        }

        foreach (['2001::/23', '2001:db8::/32', '2002::/16', '3fff::/20'] as $range) {
            if (self::inCidr($packed, $range)) {
                return false;
            }
        }

        return true;
    }

    private static function inCidr(string $packed, string $cidr): bool
    {
        [$base, $bits] = explode('/', $cidr);
        $baseBytes = inet_pton($base);

        if ($baseBytes === false || strlen($baseBytes) !== strlen($packed)) {
            return false;
        }

        $bits = (int) $bits;
        $full = intdiv($bits, 8);
        if ($full > 0 && substr($packed, 0, $full) !== substr($baseBytes, 0, $full)) {
            return false;
        }

        $rest = $bits % 8;
        if ($rest === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $rest)) & 0xFF;

        return (ord($packed[$full]) & $mask) === (ord($baseBytes[$full]) & $mask);
    }

    /** @throws UnsafeUrl */
    private function normaliseHost(string $host): string
    {
        $host = Str::lower(rtrim(trim($host, '[]'), '.'));

        if ($host === '') {
            throw new UnsafeUrl('That is not a web address.');
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $host;
        }

        if (preg_match('/[^\x20-\x7e]/', $host) === 1) {
            $ascii = function_exists('idn_to_ascii') ? idn_to_ascii($host, IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46) : false;
            if (! is_string($ascii) || $ascii === '') {
                throw new UnsafeUrl('That is not a web address.');
            }
            $host = Str::lower($ascii);
        }

        // Numbers written in unusual ways (decimal, octal, hex) are not real host names.
        if (preg_match('/^[0-9a-fx.]+$/i', $host) === 1 || ! preg_match('/^[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9-]{0,61}[a-z0-9])?)+$/', $host) || strlen($host) > 253) {
            throw new UnsafeUrl('That is not a web address.');
        }

        foreach (['localhost', 'local', 'localdomain', 'internal', 'lan', 'home', 'corp', 'intranet', 'test', 'example', 'invalid', 'onion'] as $reserved) {
            if ($host === $reserved || str_ends_with($host, '.'.$reserved)) {
                throw new UnsafeUrl('That address is not on the public internet.');
            }
        }

        return $host;
    }

    /** @return list<string> */
    private function resolve(string $host): array
    {
        if (self::$resolver !== null) {
            return array_values((self::$resolver)($host));
        }

        $ips = [];
        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
            $ips[] = $record['ip'] ?? $record['ipv6'] ?? null;
        }

        return array_values(array_filter($ips));
    }

    /** A redirect target made absolute against the page that sent it. */
    private function absolute(string $base, string $location): string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $location) === 1) {
            return $location;
        }

        $b = parse_url($base);
        $origin = $b['scheme'].'://'.$b['host'].(isset($b['port']) ? ':'.$b['port'] : '');

        if (str_starts_with($location, '//')) {
            return $b['scheme'].':'.$location;
        }
        if (str_starts_with($location, '/')) {
            return $origin.$location;
        }

        return $origin.rtrim(Str::beforeLast($b['path'] ?? '/', '/'), '/').'/'.$location;
    }

    /** @throws FetchFailed */
    private function read(StreamInterface $stream): string
    {
        $body = '';
        while (! $stream->eof()) {
            $body .= $stream->read(8192);
            if (strlen($body) > $this->maxBytes) {
                throw new FetchFailed('That page is too large to read.');
            }
        }

        return $body;
    }
}
