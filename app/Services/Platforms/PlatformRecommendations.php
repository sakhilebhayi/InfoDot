<?php

namespace App\Services\Platforms;

use App\Models\Team;
use App\Models\User;
use App\Support\Industry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Suggests platforms to a company from the rules in config/platform_recommendations.php (R-50 section 8). Every
 * suggestion carries the sentence that explains it, built from the same facts the rule tested. The facts are only
 * what InfoDot's own records say about the company; nothing is estimated. A personal workspace gets none (it cannot
 * switch a platform on), and a company for which no rule is true gets none either.
 */
class PlatformRecommendations
{
    /** The facts a rule may test with `min`. */
    public const COUNTED = ['members', 'enquiries', 'services', 'products', 'showcase_projects', 'questions', 'solutions'];

    /**
     * What InfoDot knows about the company, read from its own tables.
     *
     * @return array{industry: ?string, members: int, enquiries: int, services: int, products: int, showcase_projects: int, questions: int, solutions: int}
     */
    public function facts(Team $team): array
    {
        $id = $team->id;

        return [
            'industry' => Industry::valid($team->industry) ? (string) $team->industry : null,
            // The owner and everyone who has joined. Invitations that were not accepted are not people yet.
            'members' => 1 + DB::table('team_user')->where('team_id', $id)->where('user_id', '!=', $team->user_id)->count(),
            'enquiries' => DB::table('enquiries')->where('team_id', $id)->count(),
            'services' => DB::table('business_services')->where('team_id', $id)->where('kind', 'service')->count(),
            'products' => DB::table('business_services')->where('team_id', $id)->where('kind', 'product')->count(),
            'showcase_projects' => DB::table('business_projects')->where('team_id', $id)->count(),
            'questions' => DB::table('questions')->where('team_id', $id)->count(),
            'solutions' => DB::table('solutions')->where('team_id', $id)->count(),
        ];
    }

    /**
     * The suggestions for this company, each ['key', 'reason', 'row'] where `row` is the catalogue row.
     *
     * @return Collection<int, array{key: string, reason: string, row: array<string, mixed>}>
     */
    public function for(User $user, Team $team, CompanyCatalogue $catalogue): Collection
    {
        if (! $team->exists || $team->personal_team) {
            return collect();
        }

        $rules = (array) config('platform_recommendations.rules', []);

        if ($rules === []) {
            return collect();
        }

        $rows = $catalogue->rows($user, $team);
        $dismissed = DB::table('platform_recommendation_dismissals')->where('team_id', $team->id)->pluck('platform_key')->all();
        $facts = null;
        $found = collect();

        foreach ($rules as $rule) {
            $key = (string) ($rule['platform'] ?? '');
            $row = $rows->get($key);

            // Never a platform the company holds, cannot get, or has said no to; and one suggestion per platform.
            if ($row === null || $row['held'] || ! in_array($row['state'], CompanyCatalogue::NOT_YET_HELD, true) || in_array($key, $dismissed, true) || $found->has($key)) {
                continue;
            }

            $facts ??= $this->facts($team) + ['holds' => $rows->filter(fn (array $r) => $r['held'])->keys()->all()];

            if (! $this->holds((array) ($rule['when'] ?? []), $facts)) {
                continue;
            }

            $found->put($key, ['key' => $key, 'reason' => $this->sentence((string) ($rule['reason'] ?? ''), $team, $facts), 'row' => $row]);
        }

        return $found->filter(fn (array $r) => $r['reason'] !== '')->take(max(0, (int) config('platform_recommendations.limit', 3)))->values();
    }

    /** Record that the company does not want this suggestion. It is not shown to the company again. */
    public function dismiss(Team $team, string $key, User $by): void
    {
        DB::table('platform_recommendation_dismissals')->insertOrIgnore([
            'team_id' => $team->id, 'platform_key' => $key, 'user_id' => $by->id, 'created_at' => now(),
        ]);
    }

    /**
     * Whether every condition of a rule holds. A rule with no condition, or with a condition this class does not
     * know, never fires: a suggestion must rest on a fact.
     *
     * @param  array<string, mixed>  $when
     * @param  array<string, mixed>  $facts
     */
    public function holds(array $when, array $facts): bool
    {
        if ($when === []) {
            return false;
        }

        foreach ($when as $condition => $wanted) {
            $ok = match ($condition) {
                'industry' => $facts['industry'] !== null && in_array($facts['industry'], (array) $wanted, true),
                'min' => $wanted !== [] && collect((array) $wanted)->every(fn ($n, $fact) => in_array($fact, self::COUNTED, true) && (int) ($facts[$fact] ?? 0) >= max(1, (int) $n)),
                'holds' => $wanted !== [] && array_diff((array) $wanted, (array) ($facts['holds'] ?? [])) === [],
                default => false,
            };

            if (! $ok) {
                return false;
            }
        }

        return true;
    }

    /** @param  array<string, mixed>  $facts */
    private function sentence(string $template, Team $team, array $facts): string
    {
        $replace = [':company' => (string) $team->name, ':industry' => mb_strtolower((string) (Industry::options()[$facts['industry'] ?? ''] ?? ''))];

        foreach (self::COUNTED as $fact) {
            $replace[':'.$fact] = (string) (int) $facts[$fact];
        }

        // Longest names first, so :showcase_projects is not cut short by a shorter name.
        uksort($replace, fn ($a, $b) => strlen($b) <=> strlen($a));

        return trim(strtr($template, $replace));
    }
}
