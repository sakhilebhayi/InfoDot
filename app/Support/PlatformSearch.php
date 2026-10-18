<?php

namespace App\Support;

use App\Models\Resource;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Asks the platforms that hold a company's files and documents what matches what the person
 * typed. Signed with each platform's own secret, like the membership webhook.
 *
 * Nothing is stored here. A platform answers only with what this person may see, and a result
 * is shown only when it is a pointer InfoDot already holds for this very company and platform,
 * so a platform cannot make InfoDot link somewhere it never registered.
 */
final class PlatformSearch
{
    public const KIND_ICONS = ['file' => 'draft', 'document' => 'description'];

    /**
     * @return list<array{kind: string, label: string, url: string, platform: string, icon: string}>
     */
    public static function search(User $user, ?Team $team, string $query): array
    {
        $query = trim($query);

        if ($team === null || $team->personal_team || blank($team->uuid) || mb_strlen($query) < 3) {
            return [];
        }

        $body = (string) json_encode(['team_uuid' => (string) $team->uuid, 'email' => strtolower($user->email), 'q' => $query], JSON_UNESCAPED_SLASHES);

        $targets = [];
        foreach ((array) config('ecosystem.search_platforms', []) as $key) {
            $secret = (string) config('ecosystem.service_secrets.'.$key, '');
            $url = EcosystemHandoff::apiUrlFor((string) $key, '/api/ecosystem/search');

            if ($secret !== '' && $url !== null) {
                $targets[(string) $key] = [$url, $secret];
            }
        }

        if ($targets === []) {
            return [];
        }

        $timestamp = time();

        try {
            $responses = Http::pool(function (Pool $pool) use ($targets, $body, $timestamp) {
                foreach ($targets as $key => [$url, $secret]) {
                    $pool->as($key)->withHeaders([
                        MembershipWebhook::HEADER_TIMESTAMP => (string) $timestamp,
                        MembershipWebhook::HEADER_SIGNATURE => MembershipWebhook::sign($secret, $timestamp, $body),
                    ])->acceptJson()->timeout(3)->withBody($body, 'application/json')->post($url);
                }
            });
        } catch (Throwable $e) {
            Log::warning('Cross-platform search failed.', ['error' => class_basename($e)]);

            return [];
        }

        $found = [];

        foreach ($targets as $key => $_) {
            $response = $responses[$key] ?? null;

            if (! $response instanceof Response || ! $response->successful()) {
                continue;
            }

            foreach (array_slice((array) $response->json('results', []), 0, 5) as $row) {
                if (is_array($row) && is_string($row['resource'] ?? null) && is_string($row['title'] ?? null) && $row['title'] !== '') {
                    $found[$key][] = $row;
                }
            }
        }

        $items = [];

        foreach ($found as $key => $rows) {
            $known = Resource::where('team_id', $team->id)->where('platform', $key)->whereIn('uuid', array_column($rows, 'resource'))->pluck('type', 'uuid');

            foreach ($rows as $row) {
                $type = $known[$row['resource']] ?? null;

                if ($type === null) {
                    continue;
                }

                $items[] = [
                    'kind' => $type,
                    'label' => mb_substr($row['title'], 0, 140),
                    'url' => route('resources.open', ['resource' => $row['resource']]),
                    'platform' => (string) config('ecosystem.platforms.'.$key.'.name', $key),
                    'icon' => self::KIND_ICONS[$type] ?? 'draft',
                ];
            }
        }

        return $items;
    }
}
