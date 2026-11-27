<?php

namespace App\Support;

use Illuminate\Support\Facades\Vite;

/**
 * The content of the public Business Tools page (R-56). It joins two sources and never restates a fact of its own:
 *
 *  - database/data/platform_catalogue.php, the catalogue InfoDot already keeps for every platform: name, tagline,
 *    capabilities, declared connections and whether the platform is a core one. These are shown word for word.
 *  - config/business_tools.php, the marketing layer: the outcome headline, the everyday problem, why a business needs the
 *    platform, how it helps the business grow, and how the platforms are grouped.
 *
 * A platform appears only when the registry (config/ecosystem.php) says it is live and public, the catalogue describes it,
 * its logo is in the build and its own screen is bundled. Anything else is left out here, and a test says which.
 */
final class BusinessTools
{
    /** The most capabilities one showcase lists. The catalogue may hold more; these are its first ones, in its order. */
    public const MAX_CAPABILITIES = 5;

    /** @var array<string, array<string, mixed>>|null */
    private static ?array $catalogue = null;

    /** @return array<string, array<string, mixed>> */
    public static function catalogue(): array
    {
        return self::$catalogue ??= (array) require base_path('database/data/platform_catalogue.php');
    }

    /** Whether a platform key may be shown: live, public, described, with a logo and a screen of its own. */
    public static function shown(string $key): bool
    {
        $platform = config('ecosystem.platforms.'.$key);

        return is_array($platform)
            && ($platform['active'] ?? false)
            && ! ($platform['internal'] ?? false)
            && ! in_array($key, ['infodot', 'agents'], true)
            && isset(self::catalogue()[$key])
            && is_file(resource_path('img/landing/platforms/'.$key.'.png'))
            && is_file(resource_path('brand/platforms/'.$key.'/hero-960.webp'));
    }

    /**
     * Everything the page needs.
     *
     * @return array{pillars: array<string, array<string, string>>, groups: list<array<string, mixed>>, needs: list<array<string, mixed>>, tools: array<string, array<string, mixed>>}
     */
    public static function page(): array
    {
        $copy = (array) config('business_tools');
        $tools = [];
        $groups = [];

        foreach ((array) ($copy['groups'] ?? []) as $group) {
            $members = [];
            foreach ((array) $group['tools'] as $key) {
                if (! self::shown($key) || ! isset($copy['tools'][$key])) {
                    continue;
                }
                $tools[$key] = self::tool($key, (array) $copy['tools'][$key]);
                $members[] = $key;
            }
            if ($members === []) {
                continue;
            }
            $plate = PlatformCategories::PLATES[$group['category']] ?? ['PLTF', 'platforms'];
            $groups[] = [
                'key' => $group['key'],
                'title' => $group['title'],
                'problem' => $group['problem'],
                'together' => $group['together'],
                'plate' => ['code' => $plate[0], 'tone' => $plate[1]],
                'tools' => $members,
            ];
        }

        $needs = [];
        foreach ((array) ($copy['needs'] ?? []) as $need) {
            $keys = array_values(array_filter((array) $need['tools'], fn (string $key): bool => isset($tools[$key])));
            if ($keys !== []) {
                $needs[] = ['label' => $need['label'], 'tools' => $keys];
            }
        }

        return ['pillars' => (array) $copy['pillars'], 'groups' => $groups, 'needs' => $needs, 'tools' => $tools];
    }

    /**
     * R-73/R-74: the homepage journey (Start, Manage, Connect, Grow, Special Platforms). Start leads with InfoDot itself. Each stage carries InfoDot's own features and its live platforms, built
     * with the same tool() data the Business Tools page uses, so the homepage and that page can never describe a platform differently.
     * A platform that is not shown() is left out; a stage left with nothing at all is dropped.
     *
     * @return list<array{key: string, title: string, heading: string, line: string, note: string, infodot: list<array{title: string, body: string, url: string}>, lead: array<string, string>|null, tools: list<array<string, mixed>>}>
     */
    public static function journey(): array
    {
        $copy = (array) config('business_tools');
        $stages = [];

        foreach ((array) ($copy['journey'] ?? []) as $key => $stage) {
            $tools = [];
            foreach ((array) ($stage['tools'] ?? []) as $toolKey) {
                if (self::shown($toolKey) && isset($copy['tools'][$toolKey])) {
                    $tools[] = self::tool($toolKey, (array) $copy['tools'][$toolKey]);
                }
            }
            $infodot = array_map(fn (array $item): array => [
                'title' => (string) $item['title'],
                'body' => (string) $item['body'],
                'url' => route((string) $item['route']),
            ], (array) ($stage['infodot'] ?? []));

            $lead = null;
            if (is_array($stage['lead'] ?? null)) {
                $l = $stage['lead'];
                $lead = [
                    'name' => (string) $l['name'],
                    'role' => (string) $l['role'],
                    'outcome' => (string) $l['outcome'],
                    'problem' => (string) $l['problem'],
                    'why' => (string) $l['why'],
                    'url' => route((string) $l['route'], (array) ($l['route_params'] ?? [])),
                    'cta' => (string) $l['cta'],
                    // Owner rule 2026-11-26: the yellow-and-white logo on dark grounds, its dark-lettered twin on light grounds.
                    'logo' => Vite::asset('resources/img/brand/logo_dark.png'),
                    'logo_on_dark' => Vite::asset('resources/img/brand/logo_white.png'),
                ];
            }

            if ($tools === [] && $infodot === [] && $lead === null) {
                continue;
            }
            $stages[] = [
                'key' => (string) $key,
                'title' => (string) $stage['title'],
                'heading' => (string) ($stage['heading'] ?? ''),
                'line' => (string) $stage['line'],
                'feel' => (string) ($stage['feel'] ?? ''),
                'note' => (string) ($stage['note'] ?? ''),
                'infodot' => $infodot,
                'lead' => $lead,
                'tools' => $tools,
            ];
        }

        return $stages;
    }

    /**
     * @param  array<string, mixed>  $words  the marketing layer for this platform
     * @return array<string, mixed>
     */
    private static function tool(string $key, array $words): array
    {
        $entry = self::catalogue()[$key];

        return array_merge($words, [
            'key' => $key,
            'name' => (string) $entry['name'],
            'tagline' => (string) $entry['tagline'],
            'capabilities' => array_slice((array) $entry['capabilities'], 0, self::MAX_CAPABILITIES),
            'connects' => array_values(array_filter(array_map(fn (string $other): ?array => $other === 'infodot' ? null : [
                'key' => $other,
                'name' => (string) (self::catalogue()[$other]['name'] ?? 'Dot.'.ucfirst($other)),
            ], (array) ($entry['connects_with'] ?? [])))),
            'core' => (bool) ($entry['is_core'] ?? false),
            'logo' => Vite::asset('resources/img/landing/platforms/'.$key.'.png'),
            'screen' => [
                'src' => route('branding.asset', ['slug' => $key, 'file' => 'hero-960.webp']),
                'srcset' => collect([480, 960, 1600])->map(fn (int $w): string => route('branding.asset', ['slug' => $key, 'file' => "hero-{$w}.webp"])." {$w}w")->implode(', '),
            ],
        ]);
    }
}
