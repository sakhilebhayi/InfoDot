<?php

namespace App\Services\Platforms;

use App\Support\Platforms;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives every platform a person can see its catalogue row (R-50). Run by the migration that creates the table and by
 * `platforms:sync` afterwards. It only ever adds a missing row: a row that exists belongs to the platform
 * administrators and is never overwritten, and a row whose platform left the registry is left for them to see.
 * Plain queries, so it also runs inside a migration before any model is safe to use.
 */
class CatalogueSync
{
    /**
     * The written catalogue: one entry per visible registry key.
     *
     * @return array<string, array<string, mixed>>
     */
    public function entries(): array
    {
        return require database_path('data/platform_catalogue.php');
    }

    /** @return list<string> the keys that were added */
    public function sync(): array
    {
        if (! Schema::hasTable('platform_catalogue')) {
            return [];
        }

        $entries = $this->entries();
        $existing = DB::table('platform_catalogue')->pluck('key')->all();
        $added = [];

        foreach (Platforms::visible() as $key => $platform) {
            $key = (string) $key;

            if (in_array($key, $existing, true)) {
                continue;
            }

            $entry = $entries[$key] ?? [];

            DB::table('platform_catalogue')->insertOrIgnore([
                'key' => $key,
                'name' => (string) ($entry['name'] ?? $platform['name'] ?? $key),
                'tagline' => $entry['tagline'] ?? null,
                'description' => $entry['description'] ?? null,
                'benefit' => $entry['benefit'] ?? null,
                'audience' => $entry['audience'] ?? null,
                'capabilities' => json_encode($entry['capabilities'] ?? [], JSON_UNESCAPED_SLASHES),
                'categories' => json_encode($entry['categories'] ?? [], JSON_UNESCAPED_SLASHES),
                'connects_with' => json_encode($entry['connects_with'] ?? [], JSON_UNESCAPED_SLASHES),
                // A platform with no written entry is registered but not described: it is not offered until someone describes it.
                'operational_status' => $entry === [] ? 'not_launched' : 'operational',
                'discoverable' => $entry !== [],
                'is_core' => (bool) ($entry['is_core'] ?? false),
                'sort' => (int) ($entry['sort'] ?? 999),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $added[] = $key;
        }

        return $added;
    }
}
