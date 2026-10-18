<?php

namespace App\Support;

/**
 * The platforms people may see and open.
 *
 * Some Dot services exist only to connect the others (shared memory, AI, platform
 * connectivity). They stay in the registry, because the platforms still talk to them, but
 * they are never listed or launched for a person. A registry entry is hidden when it carries
 * `internal => true`; the keys below are hidden even if a copy of the registry predates the flag.
 */
final class Platforms
{
    /** Bridge services: never shown to people, on any platform. */
    public const INTERNAL = ['memory', 'brain', 'dopemine'];

    public static function isInternal(string $key, ?array $platform = null): bool
    {
        $platform ??= config('ecosystem.platforms.'.$key);

        return in_array($key, self::INTERNAL, true) || ($platform['internal'] ?? false) === true;
    }

    /**
     * Everything a person can see: the registry without the internal bridge services.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function visible(): array
    {
        return array_filter(
            (array) config('ecosystem.platforms', []),
            fn ($platform, $key) => ! self::isInternal((string) $key, $platform),
            ARRAY_FILTER_USE_BOTH
        );
    }
}
