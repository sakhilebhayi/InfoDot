<?php

namespace App\Support\Knowledge;

use App\Support\Platforms;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The starting list of topics: a handful that cut across the ecosystem, then one per Dot platform a person can see
 * (the registry in config/ecosystem.php, without the internal bridge services). Moderators curate the list from there.
 *
 * `seed()` only ever adds: a topic whose slug already exists is left exactly as it is, so running it again, or after a
 * moderator renamed or retired a topic, changes nothing.
 */
final class TopicCatalogue
{
    /** @return list<array{slug: string, name: string, description: string, sort_order: int}> */
    public static function defaults(): array
    {
        $topics = [
            ['slug' => 'getting-started', 'name' => 'Getting started', 'description' => 'First steps: setting up a company, inviting people, finding your way around.'],
            ['slug' => 'sign-in-and-accounts', 'name' => 'Sign-in & accounts', 'description' => 'Signing in, passwords, two-factor, switching company, and your account settings.'],
            ['slug' => 'billing-and-payments', 'name' => 'Billing & payments', 'description' => 'Invoices, subscriptions, payment methods and what you are charged for.'],
            ['slug' => 'data-and-reports', 'name' => 'Data & reports', 'description' => 'Getting information in and out, exports, reports and numbers that do not add up.'],
            ['slug' => 'integrations', 'name' => 'Integrations', 'description' => 'Connecting platforms to each other and to tools outside the ecosystem.'],
        ];

        $order = 0;
        $topics = array_map(fn (array $t) => $t + ['sort_order' => ($order += 10)], $topics);

        $order = 100;
        foreach (Platforms::visible() as $key => $platform) {
            $name = (string) ($platform['name'] ?? $key);
            $topics[] = [
                'slug' => (string) $key,
                'name' => $name,
                'description' => 'Questions and solutions about '.$name.'.',
                'sort_order' => ($order += 10),
            ];
        }

        return $topics;
    }

    /** Add any starting topic that is not there yet. Returns how many were added. */
    public static function seed(): int
    {
        if (! Schema::hasTable('topics')) {
            return 0;
        }

        $now = now();
        $added = 0;

        foreach (self::defaults() as $topic) {
            $added += DB::table('topics')->insertOrIgnore($topic + ['active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }

        return $added;
    }
}
