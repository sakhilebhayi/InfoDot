<?php

namespace App\Services\Growth;

use App\Models\AcquisitionTouch;
use App\Models\User;
use App\Services\Sso\PlatformContext;
use Illuminate\Http\Request;

/**
 * R-59 Phase 1A: reads where a visitor came from, keeps it in one encrypted first-party cookie, and turns it into a single row when they
 * register. Only an arriving visit with a referral code, a campaign tag or a search or social referrer is kept; a plain visit sets nothing.
 * Everything read from the address or the cookie is validated and trimmed again before it is stored.
 */
class Acquisition
{
    /** @return array{r: ?string, u: array<string, string>, h: ?string, p: ?string, at: int}|null the arriving visit, or null when it carries nothing to keep */
    public function fromArrival(Request $request): ?array
    {
        $ref = $this->referralCode($request->query('ref'));
        $utm = $this->utm($request);
        $host = $this->referrerHost($request);

        if ($ref === null && $utm === [] && ($host === null || ! $this->isOrganicHost($host))) {
            return null;
        }

        return ['r' => $ref, 'u' => $utm, 'h' => $host, 'p' => $this->landingPath($request), 'at' => time()];
    }

    /** @return array{r: ?string, u: array<string, string>, h: ?string, p: ?string, at: int}|null the visit the cookie holds, checked again */
    public function fromCookie(Request $request): ?array
    {
        $raw = $request->cookie((string) config('acquisition.cookie'));

        if (! is_string($raw) || $raw === '' || strlen($raw) > 1200) {
            return null;
        }

        $data = json_decode($raw, true);

        if (! is_array($data) || ! is_int($data['at'] ?? null) || $data['at'] > time() + 60 || $data['at'] < time() - config('acquisition.days') * 86400) {
            return null;
        }

        $utm = [];
        foreach ((array) ($data['u'] ?? []) as $key => $value) {
            if (in_array($key, config('acquisition.utm_fields'), true) && ($clean = $this->tag($value)) !== null) {
                $utm[$key] = $clean;
            }
        }

        $host = is_string($data['h'] ?? null) && preg_match('/^[a-z0-9.-]{3,120}$/', $data['h']) ? $data['h'] : null;
        $path = is_string($data['p'] ?? null) && strlen($data['p']) <= 190 && str_starts_with($data['p'], '/') ? $data['p'] : null;

        return ['r' => $this->referralCode($data['r'] ?? null), 'u' => $utm, 'h' => $host, 'p' => $path, 'at' => $data['at']];
    }

    /** The channel of an account: the platform that started the sign-in, a referral, a named medium, a tagged link, a search or social referral, else direct. */
    public function classify(?array $touch, ?string $platformSlug): string
    {
        if ($platformSlug !== null) {
            return 'product_led';
        }

        if ($touch === null) {
            return 'direct';
        }

        if ($touch['r'] !== null) {
            return 'referral';
        }

        $medium = strtolower((string) ($touch['u']['medium'] ?? ''));
        if ($medium !== '' && isset(config('acquisition.medium_channels')[$medium])) {
            return config('acquisition.medium_channels')[$medium];
        }

        if ($touch['u'] !== []) {
            return 'campaign';
        }

        return $touch['h'] !== null && $this->isOrganicHost($touch['h']) ? 'organic' : 'direct';
    }

    /** One row for the new account, never more (the user id is unique). It must never stop a registration, so a failure is reported and swallowed. */
    public function record(User $user, Request $request): ?AcquisitionTouch
    {
        try {
            $touch = $this->fromCookie($request);
            $platform = $request->hasSession() ? app(PlatformContext::class)->platform()?->slug : null;

            return AcquisitionTouch::firstOrCreate(['user_id' => $user->id], [
                'channel' => $this->classify($touch, $platform),
                'referral_code' => $touch['r'] ?? null,
                'utm_source' => $touch['u']['source'] ?? null,
                'utm_medium' => $touch['u']['medium'] ?? null,
                'utm_campaign' => $touch['u']['campaign'] ?? null,
                'utm_term' => $touch['u']['term'] ?? null,
                'utm_content' => $touch['u']['content'] ?? null,
                'referrer_host' => $touch['h'] ?? null,
                'landing_path' => $touch['p'] ?? null,
                'platform_slug' => $platform,
                'touched_at' => isset($touch['at']) ? now()->setTimestamp($touch['at']) : null,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    private function referralCode(mixed $value): ?string
    {
        return is_string($value) && preg_match((string) config('acquisition.ref_pattern'), $value) === 1 ? $value : null;
    }

    /** @return array<string, string> */
    private function utm(Request $request): array
    {
        $out = [];

        foreach (config('acquisition.utm_fields') as $field) {
            if (($clean = $this->tag($request->query('utm_'.$field))) !== null) {
                $out[$field] = $clean;
            }
        }

        return $out;
    }

    private function tag(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' && preg_match('/^[\p{L}\p{N} _.\-+\/]+$/u', $value) === 1 ? mb_substr($value, 0, (int) config('acquisition.utm_max')) : null;
    }

    private function referrerHost(Request $request): ?string
    {
        $host = strtolower((string) parse_url((string) $request->headers->get('referer'), PHP_URL_HOST));

        if ($host === '' || strcasecmp($host, $request->getHost()) === 0 || str_ends_with($host, '.'.$request->getHost()) || preg_match('/^[a-z0-9.-]{3,120}$/', $host) !== 1) {
            return null;
        }

        return $host;
    }

    private function isOrganicHost(string $host): bool
    {
        foreach (config('acquisition.organic_hosts') as $needle) {
            if (str_contains($host, $needle)) {
                return true;
            }
        }

        return false;
    }

    /** The path only: no query string, no fragment, nothing a person typed. */
    private function landingPath(Request $request): string
    {
        return mb_substr('/'.ltrim($request->path() === '/' ? '' : $request->path(), '/'), 0, 190);
    }
}
