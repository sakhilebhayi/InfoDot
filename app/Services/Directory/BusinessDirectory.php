<?php

namespace App\Services\Directory;

use App\Models\BusinessCategory;
use App\Models\BusinessService;
use App\Models\BusinessVerification;
use App\Models\Team;
use App\Models\TeamProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The business directory (R-33 §2–3). Searches only what businesses published about themselves —
 * names, description, industry, categories, services, location and regions — and only businesses
 * the viewer may see. Natural queries work: "accounting firms in Johannesburg" searches
 * "accounting" and keeps businesses located in Johannesburg. Every result carries the reasons it
 * matched, so search is explainable; nothing is inferred or invented.
 *
 * Matching is done in two steps — SQL narrows to candidates that contain at least one search word
 * anywhere relevant, then each candidate is scored by where the words appear. That is quick and
 * honest at InfoDot's size; the method is the one place to swap in a full-text engine later.
 */
final class BusinessDirectory
{
    private const PER_PAGE = 24;

    private const CANDIDATES = 600;

    /** Words that say "a business" rather than which one. */
    private const NOISE = ['a', 'an', 'the', 'and', 'or', 'of', 'for', 'to', 'in', 'near', 'around', 'with', 'that', 'who', 'which',
        'company', 'companies', 'business', 'businesses', 'firm', 'firms', 'offering', 'offers', 'offer', 'provider', 'providers', 'services', 'service', 'find', 'me', 'my', 'looking'];

    /** Where a word was found, and what that is worth. */
    private const WEIGHTS = ['name' => 6, 'category' => 4, 'service' => 4, 'industry' => 3, 'service_detail' => 2, 'offering' => 2, 'description' => 1];

    private const LABELS = ['name' => 'name', 'category' => 'category', 'service' => 'service', 'industry' => 'industry', 'service_detail' => 'service description', 'offering' => 'products and services', 'description' => 'description'];

    /**
     * @param  array{q?: string|null, category?: string|null, location?: string|null, region?: string|null, size?: string|null, type?: string|null, verified?: bool|null, sort?: string|null, view?: string|null, page?: int|null, all?: bool|null}  $f
     * @return array{results: LengthAwarePaginator<int, Team>, reasons: array<int, list<string>>, words: list<string>, location: ?string, service_hits: array<int, list<int>>}
     */
    public function search(array $f, ?User $viewer): array
    {
        [$words, $location] = $this->parse((string) ($f['q'] ?? ''));
        $location = trim((string) ($f['location'] ?? '')) ?: $location;

        $query = Team::query()->listedFor($viewer)
            ->with(['profile', 'categories', 'services', 'standing', 'logo']);

        if ($f['category'] ?? null) {
            $query->whereHas('categories', fn (Builder $c) => $c->where('slug', $f['category']));
        }
        if ($location) {
            // Where it says it is, where it says it works, or any location it lists (R-33 Phase 2).
            $like = '%'.$location.'%';
            $query->where(fn (Builder $w) => $w
                ->whereHas('profile', fn (Builder $p) => $p->where(fn ($p) => $p->where('location', 'like', $like)->orWhere('regions', 'like', $like)))
                ->orWhereHas('locations', fn (Builder $l) => $l->where('city', 'like', $like)->orWhere('province', 'like', $like)));
        }
        if ($f['region'] ?? null) {
            $query->whereHas('profile', fn (Builder $p) => $p->where('regions', 'like', '%'.$f['region'].'%'));
        }
        if ($f['size'] ?? null) {
            $query->whereHas('profile', fn (Builder $p) => $p->where('size', $f['size']));
        }
        if ($f['type'] ?? null) {
            $query->whereHas('profile', fn (Builder $p) => $p->where('business_type', $f['type']));
        }
        if ($f['verified'] ?? false) {
            $query->whereHas('verifications', fn (Builder $v) => $v->where('status', BusinessVerification::VERIFIED)->where('expires_at', '>', now())->whereNotNull('verified_at'));
        }

        if ($words !== []) {
            $query->where(function (Builder $q) use ($words) {
                foreach ($words as $w) {
                    $like = '%'.$w.'%';
                    $q->orWhere('name', 'like', $like)
                        ->orWhereHas('profile', fn (Builder $p) => $p->where(fn ($p) => $p->where('trading_name', 'like', $like)->orWhere('description', 'like', $like)
                            ->orWhere('industry', 'like', $like)->orWhere('category', 'like', $like)->orWhere('products_services', 'like', $like)))
                        ->orWhereHas('services', fn (Builder $s) => $s->where('name', 'like', $like)->orWhere('description', 'like', $like))
                        ->orWhereHas('categories', fn (Builder $c) => $c->where('name', 'like', $like));
                }
            });
        }

        $sort = $f['sort'] ?? ($words ? 'relevance' : 'newest');
        $candidates = $query->limit(self::CANDIDATES)->get();

        $reasons = [];
        $scores = [];
        $serviceHits = [];
        foreach ($candidates as $team) {
            [$score, $why] = $this->score($team, $words);
            $scores[$team->id] = $score;
            $reasons[$team->id] = $why;
            // Which of its services the words found, for the business's "popular services" insight.
            $serviceHits[$team->id] = $words === [] ? [] : $team->services
                ->filter(fn (BusinessService $s) => collect($words)->contains(fn ($w) => str_contains(mb_strtolower($s->name.' '.$s->description), $w)))
                ->pluck('id')->all();
        }

        $sorted = match ($sort) {
            'name' => $candidates->sortBy(fn (Team $t) => mb_strtolower($t->name)),
            'newest' => $candidates->sortByDesc(fn (Team $t) => $t->created_at?->getTimestamp() ?? 0),
            default => $candidates->sortByDesc(fn (Team $t) => [$scores[$t->id], (int) $t->isVerified(), $t->created_at?->getTimestamp() ?? 0]),
        };

        $page = max(1, (int) ($f['page'] ?? 1));
        $results = new LengthAwarePaginator($sorted->values()->forPage($page, self::PER_PAGE), $sorted->count(), self::PER_PAGE, $page, [
            'path' => route('businesses.index'),
            'query' => array_filter(['q' => $f['q'] ?? null, 'category' => $f['category'] ?? null, 'location' => $f['location'] ?? null, 'region' => $f['region'] ?? null,
                'size' => $f['size'] ?? null, 'type' => $f['type'] ?? null, 'verified' => ($f['verified'] ?? false) ? 1 : null, 'sort' => $f['sort'] ?? null, 'view' => $f['view'] ?? null, 'all' => ($f['all'] ?? false) ? 1 : null]),
        ]);

        return ['results' => $results, 'reasons' => $reasons, 'words' => $words, 'location' => $location ?: null, 'service_hits' => $serviceHits];
    }

    /** What to show before anyone searches: categories with listed businesses, the newest, and services on offer. @return array{categories: Collection<int, BusinessCategory>, newest: Collection<int, Team>, services: Collection<int, object>} */
    public function browse(?User $viewer): array
    {
        $listed = Team::query()->listedFor($viewer)->select('id');

        return [
            'categories' => BusinessCategory::where('active', true)
                ->withCount(['teams' => fn (Builder $t) => $t->whereIn('teams.id', $listed)])
                ->orderByDesc('teams_count')->orderBy('sort')->get(),
            'newest' => Team::query()->listedFor($viewer)->with(['profile', 'categories', 'services', 'standing', 'logo'])->latest('id')->limit(6)->get(),
            'services' => BusinessService::query()->whereIn('team_id', $listed)
                ->selectRaw('lower(name) as name, count(distinct team_id) as businesses')->groupByRaw('lower(name)')
                ->orderByDesc('businesses')->orderBy('name')->limit(18)->get(),
        ];
    }

    /**
     * Businesses like this one (R-33 §8): listed for this viewer, sharing a category or the words of
     * a service. Each comes with what it shares, so the suggestion explains itself.
     *
     * @return list<array{team: Team, why: list<string>}>
     */
    public function similar(Team $team, ?User $viewer, int $limit = 4): array
    {
        $categoryIds = $team->categories->pluck('id')->all();
        $words = $team->services->flatMap(fn (BusinessService $s) => preg_split('/[^a-z0-9]+/', mb_strtolower($s->name)) ?: [])
            ->filter(fn ($w) => strlen($w) >= 4 && ! in_array($w, self::NOISE, true))->unique()->values()->all();

        if ($categoryIds === [] && $words === []) {
            return [];
        }

        $candidates = Team::query()->listedFor($viewer)->whereKeyNot($team->id)
            ->where(function (Builder $q) use ($categoryIds, $words) {
                if ($categoryIds !== []) {
                    $q->orWhereHas('categories', fn (Builder $c) => $c->whereIn('business_categories.id', $categoryIds));
                }
                foreach ($words as $w) {
                    $q->orWhereHas('services', fn (Builder $s) => $s->where('name', 'like', '%'.$w.'%'));
                }
            })
            ->with(['profile', 'categories', 'services', 'standing', 'logo'])->limit(200)->get();

        return $candidates->map(function (Team $other) use ($categoryIds, $words) {
            $sharedCats = $other->categories->whereIn('id', $categoryIds);
            $sharedServices = $other->services->filter(fn (BusinessService $s) => collect($words)->contains(fn ($w) => str_contains(mb_strtolower($s->name), $w)));
            $why = [];
            if ($sharedCats->isNotEmpty()) {
                $why[] = 'Same category: '.$sharedCats->pluck('name')->take(2)->implode(', ');
            }
            if ($sharedServices->isNotEmpty()) {
                $why[] = 'Also offers: '.$sharedServices->pluck('name')->take(2)->implode(', ');
            }

            return ['team' => $other, 'why' => $why, 'score' => $sharedCats->count() * 3 + $sharedServices->count()];
        })->sortByDesc('score')->take($limit)->map(fn ($r) => ['team' => $r['team'], 'why' => $r['why']])->values()->all();
    }

    /**
     * Places listed businesses say they are in (R-33 §8 "explore geographically"), from what they
     * typed: their locations' towns and provinces, and the town in their profile location. Without
     * verified coordinates InfoDot does not draw a map; it lists places that lead to a search.
     *
     * @return array{towns: list<array{place: string, businesses: int}>, provinces: list<array{place: string, businesses: int}>}
     */
    public function places(?User $viewer): array
    {
        $listed = Team::query()->listedFor($viewer)->select('id');

        $fromLocations = DB::table('business_locations')->whereIn('team_id', $listed)->get(['team_id', 'city', 'province']);
        $fromProfiles = TeamProfile::query()->whereIn('team_id', Team::query()->listedFor($viewer)->select('id'))->whereNotNull('location')->get(['team_id', 'location']);

        $towns = collect();
        foreach ($fromLocations as $l) {
            $towns->push(['place' => trim((string) $l->city), 'team' => $l->team_id]);
        }
        foreach ($fromProfiles as $p) {
            $towns->push(['place' => trim(explode(',', (string) $p->location)[0]), 'team' => $p->team_id]);
        }

        $count = fn (Collection $rows) => $rows->filter(fn ($r) => mb_strlen($r['place']) >= 2)
            ->groupBy(fn ($r) => mb_convert_case(mb_strtolower($r['place']), MB_CASE_TITLE))
            ->map(fn ($g, $place) => ['place' => (string) $place, 'businesses' => $g->pluck('team')->unique()->count()])
            ->sortByDesc('businesses')->values();

        $provinces = $fromLocations->filter(fn ($l) => filled($l->province))->map(fn ($l) => ['place' => trim((string) $l->province), 'team' => $l->team_id]);

        return ['towns' => $count($towns)->take(12)->values()->all(), 'provinces' => $count(collect($provinces->all()))->take(9)->values()->all()];
    }

    /** The sizes listed businesses actually gave, for the size filter. @return Collection<int, string> */
    public function sizes(?User $viewer): Collection
    {
        return TeamProfile::query()->whereIn('team_id', Team::query()->listedFor($viewer)->select('id'))
            ->whereNotNull('size')->where('size', '!=', '')
            ->distinct()->orderBy('size')->limit(20)->pluck('size');
    }

    /** "Mining equipment suppliers in Mpumalanga" → (['mining', 'equipment', 'supplier'], 'Mpumalanga'). @return array{0: list<string>, 1: ?string} */
    public function parse(string $q): array
    {
        $q = trim(preg_replace('/\s+/', ' ', Str::ascii($q)) ?? '');
        $location = null;

        if (preg_match('/\b(?:in|near|around|based in)\s+([A-Za-z][A-Za-z\s\-\']{1,60})$/i', rtrim($q, '.?! '), $m)) {
            $location = trim($m[1]);
            $q = trim(substr($q, 0, -strlen($m[0]) - (strlen(rtrim($q, '.?! ')) === strlen($q) ? 0 : strlen($q) - strlen(rtrim($q, '.?! ')))));
        }

        $words = [];
        foreach (preg_split('/[^a-z0-9]+/', mb_strtolower($q)) ?: [] as $w) {
            if (strlen($w) < 2 || in_array($w, self::NOISE, true)) {
                continue;
            }
            // Plural to singular, so "suppliers" finds "supplier" and "firms" finds "firm".
            $words[] = strlen($w) > 4 && str_ends_with($w, 's') && ! str_ends_with($w, 'ss') ? substr($w, 0, -1) : $w;
        }

        return [array_values(array_unique($words)), $location];
    }

    /** @param list<string> $words @return array{0: int, 1: list<string>} */
    private function score(Team $team, array $words): array
    {
        if ($words === []) {
            return [0, []];
        }

        $p = $team->profile;
        $fields = [
            'name' => [$team->name, $p?->trading_name],
            'category' => $team->categories->pluck('name')->all(),
            'service' => $team->services->pluck('name')->all(),
            'industry' => [$p?->industry, $p?->category],
            'service_detail' => $team->services->pluck('description')->all(),
            'offering' => [$p?->products_services],
            'description' => [$p?->description],
        ];

        $score = 0;
        $why = [];
        foreach ($words as $w) {
            foreach ($fields as $field => $values) {
                $hit = collect($values)->filter()->first(fn ($v) => str_contains(mb_strtolower((string) $v), $w));
                if ($hit !== null) {
                    $score += self::WEIGHTS[$field];
                    $why[$field] ??= $field === 'service' || $field === 'category' ? self::LABELS[$field].' “'.Str::limit((string) $hit, 40).'”' : self::LABELS[$field];
                    break; // a word counts once, where it matters most
                }
            }
        }

        return [$score, array_values($why)];
    }
}
