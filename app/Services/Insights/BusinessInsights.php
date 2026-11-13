<?php

namespace App\Services\Insights;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What a business's InfoDot presence does for it (R-33 §9), from recorded events only. Every count
 * is a daily total per business; no visitor, account or address is stored with it. A view is one
 * per visitor per day; the business's own people, reviewers and previews are not counted; known
 * crawlers are not counted. Search terms are the parsed words of a search, and a term is shown to
 * the business only once it has been seen at least TERM_THRESHOLD times.
 */
final class BusinessInsights
{
    public const METRICS = ['view', 'search', 'call', 'email', 'website', 'map'];

    public const CONTACT = ['call' => 'Calls', 'email' => 'Emails', 'website' => 'Website visits', 'map' => 'Map and directions'];

    public const TERM_THRESHOLD = 3;

    private const BOTS = '/bot|crawl|spider|slurp|preview|facebookexternalhit|curl|wget|python|headless|lighthouse/i';

    /** Whether this request should count at all: a real visitor outside the business. */
    public function counts(Team $team, ?User $viewer, ?string $userAgent): bool
    {
        if ($userAgent === null || $userAgent === '' || preg_match(self::BOTS, $userAgent)) {
            return false;
        }

        return $viewer === null || (! $viewer->belongsToTeam($team) && ! $viewer->isModerator());
    }

    /** Add one to a metric; with $oncePerVisitor, at most once per visitor (their session) per day. */
    public function bump(Team $team, string $metric, bool $oncePerVisitor = true, int $by = 1): void
    {
        if ($oncePerVisitor) {
            $key = 'ins.'.$team->id.'.'.$metric.'.'.now()->toDateString();
            if (session()->has($key)) {
                return;
            }
            session()->put($key, true);
        }

        $this->upsert('business_stats_daily', ['team_id' => $team->id, 'day' => now()->toDateString(), 'metric' => mb_substr($metric, 0, 24)], $by);
    }

    /**
     * A page of directory results was shown: each business on it appeared in a search, for these
     * words, and matched these services. Once per visitor per query per day.
     *
     * @param  iterable<Team>  $teams
     * @param  list<string>  $words
     * @param  array<int, list<int>>  $serviceHits  team id => service ids that matched
     */
    public function searched(iterable $teams, array $words, array $serviceHits): void
    {
        $query = implode(' ', $words);
        $key = 'ins.search.'.md5($query).'.'.now()->toDateString();
        if (session()->has($key)) {
            return;
        }
        session()->put($key, true);

        // One statement per table for the whole page, not two or three per business on it (measured: 96 writes for
        // a page of 24). The unique keys on both tables make the add-or-insert a single upsert.
        $day = now()->toDateString();
        $stats = [];
        $terms = [];
        foreach ($teams as $team) {
            $stats[] = ['team_id' => $team->id, 'day' => $day, 'metric' => 'search', 'count' => 1];
            if ($query !== '') {
                $terms[] = ['team_id' => $team->id, 'day' => $day, 'term' => mb_substr($query, 0, 80), 'count' => 1];
            }
            foreach (array_unique($serviceHits[$team->id] ?? []) as $serviceId) {
                $stats[] = ['team_id' => $team->id, 'day' => $day, 'metric' => 'service:'.$serviceId, 'count' => 1];
            }
        }

        $this->addAll('business_stats_daily', $stats, ['team_id', 'day', 'metric']);
        $this->addAll('business_search_terms_daily', $terms, ['team_id', 'day', 'term']);
    }

    /**
     * Add one to each row's count, inserting the rows that do not exist yet, in a single statement.
     *
     * @param  list<array<string, int|string>>  $rows
     * @param  list<string>  $uniqueBy
     */
    private function addAll(string $table, array $rows, array $uniqueBy): void
    {
        if ($rows === []) {
            return;
        }

        $count = DB::getQueryGrammar()->wrap($table.'.count');

        DB::table($table)->upsert($rows, $uniqueBy, ['count' => DB::raw($count.' + 1')]);
    }

    /**
     * The insights page: totals, a daily series, contact actions, terms, services.
     *
     * @return array{since: ?Carbon, days: int, totals: array<string, int>, previous: array<string, int>, series: list<array{day: string, view: int, search: int}>, terms: list<array{term: string, count: int}>, other_terms: int, services: list<array{id: int, count: int}>}
     */
    public function report(Team $team, int $days = 30): array
    {
        $from = now()->subDays($days - 1)->toDateString();
        $prevFrom = now()->subDays($days * 2 - 1)->toDateString();

        $rows = DB::table('business_stats_daily')->where('team_id', $team->id)->where('day', '>=', $prevFrom)->get(['day', 'metric', 'count']);
        $current = $rows->filter(fn ($r) => (string) $r->day >= $from);
        $previous = $rows->filter(fn ($r) => (string) $r->day < $from);

        $sum = fn ($set, string $metric) => (int) $set->where('metric', $metric)->sum('count');

        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = now()->subDays($i)->toDateString();
            $series[] = ['day' => $d, 'view' => (int) $current->where('day', $d)->where('metric', 'view')->sum('count'), 'search' => (int) $current->where('day', $d)->where('metric', 'search')->sum('count')];
        }

        $terms = DB::table('business_search_terms_daily')->where('team_id', $team->id)->where('day', '>=', $from)
            ->selectRaw('term, sum(count) as n')->groupBy('term')->orderByDesc('n')->get();
        $shown = $terms->filter(fn ($t) => (int) $t->n >= self::TERM_THRESHOLD)->take(15);

        $services = $current->filter(fn ($r) => str_starts_with((string) $r->metric, 'service:'))
            ->groupBy('metric')->map(fn ($g, $m) => ['id' => (int) substr((string) $m, 8), 'count' => (int) $g->sum('count')])
            ->sortByDesc('count')->values()->take(10)->all();

        $since = DB::table('business_stats_daily')->where('team_id', $team->id)->min('day');

        return [
            'since' => $since ? Carbon::parse($since) : null,
            'days' => $days,
            'totals' => collect(self::METRICS)->mapWithKeys(fn ($m) => [$m => $sum($current, $m)])->all(),
            'previous' => collect(self::METRICS)->mapWithKeys(fn ($m) => [$m => $sum($previous, $m)])->all(),
            'series' => $series,
            'terms' => $shown->map(fn ($t) => ['term' => (string) $t->term, 'count' => (int) $t->n])->values()->all(),
            'other_terms' => (int) $terms->reject(fn ($t) => (int) $t->n >= self::TERM_THRESHOLD)->sum('n'),
            'services' => $services,
        ];
    }

    /** @param array<string, mixed> $keys */
    private function upsert(string $table, array $keys, int $by): void
    {
        $updated = DB::table($table)->where($keys)->increment('count', $by);
        if ($updated === 0) {
            try {
                DB::table($table)->insert($keys + ['count' => $by]);
            } catch (UniqueConstraintViolationException) {
                DB::table($table)->where($keys)->increment('count', $by); // a parallel request inserted it first
            }
        }
    }
}
