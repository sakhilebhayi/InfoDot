<?php

namespace App\Services\Reputation;

use App\Models\Answer;
use App\Models\LibraryEntry;
use App\Models\Questions;
use App\Models\ReputationAppeal;
use App\Models\ReputationRingFlag;
use App\Models\ReputationSnapshot;
use App\Models\SeminarRegistration;
use App\Models\Solutions;
use App\Models\User;
use App\Services\Knowledge\AchievementService;
use App\Services\Seminars\SeminarService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Works out a person's six-dimension knowledge score and tier from facts already in InfoDot, by the rules in config/reputation.php.
 * `compute()` is pure: same facts and same rules give the same answer, and it returns the evidence behind every number so the person
 * (and a moderator reviewing an appeal) can see exactly why. `snapshot()` stores a result only when it differs from the last one, so
 * the history is a true audit trail. Nothing here is shown to anyone but the person, and nothing here grants permissions.
 */
class ReputationEngine
{
    public function __construct(private readonly AchievementService $achievements, private readonly SeminarService $seminars) {}

    /**
     * @return array{rules_version: int, total: int, tier: string, tier_name: string, next: ?array{name: string, needed: int, remaining: int}, dimensions: array<string, array{label: string, what: string, points: float, cap: int, weight: float, score: float, evidence: array<string, float|int>}>}
     */
    public function compute(User $user): array
    {
        $rules = config('reputation.points');
        $evidence = $this->evidence($user);
        $dims = [];

        foreach (config('reputation.dimensions') as $key => $def) {
            $dims[$key] = ['label' => $def['label'], 'what' => $def['what'], 'points' => 0.0, 'cap' => (int) $def['cap'], 'weight' => (float) $def['weight'], 'score' => 0.0, 'evidence' => []];
        }

        foreach ($rules as $rule => $r) {
            $count = (float) ($evidence[$rule] ?? 0);

            if (isset($r['max_count'])) {
                $count = min($count, (float) $r['max_count']);
            }

            $dims[$r['dimension']]['evidence'][$rule] = $count;
            $dims[$r['dimension']]['points'] += $count * $r['each'];
        }

        $total = 0.0;
        foreach ($dims as $key => &$d) {
            $d['points'] = max(0.0, min((float) $d['cap'], $d['points']));
            $d['score'] = round($d['points'] * $d['weight'], 1);
            $total += $d['score'];
        }
        unset($d);

        $total = (int) round($total);
        [$tier, $name, $next] = $this->tierFor($total);

        return ['rules_version' => (int) config('reputation.version'), 'total' => $total, 'tier' => $tier, 'tier_name' => $name, 'next' => $next, 'dimensions' => $dims];
    }

    /** Stores the result if it differs from the last snapshot. Nothing is stored for a person who opted out. @return ?ReputationSnapshot the current snapshot */
    public function snapshot(User $user): ?ReputationSnapshot
    {
        // Asked of the database, not of the model in hand: a person who switches the score off while a long run is under way must not get a
        // snapshot written afterwards (forget() has already deleted theirs).
        if ($user->optedOutOfReputation() || User::whereKey($user->id)->value('reputation_opt_out')) {
            return null;
        }

        $r = $this->compute($user);
        $last = ReputationSnapshot::where('user_id', $user->id)->latest('id')->first();
        if (User::whereKey($user->id)->value('reputation_opt_out')) {
            return null;
        }

        $dims = collect($r['dimensions'])->map(fn ($d) => ['points' => $d['points'], 'score' => $d['score'], 'evidence' => $d['evidence']])->all();

        if ($last && $last->rules_version === $r['rules_version'] && $last->total === $r['total'] && $last->tier === $r['tier'] && $last->dimensions == $dims) {
            return $last;
        }

        return ReputationSnapshot::create(['user_id' => $user->id, 'rules_version' => $r['rules_version'], 'total' => $r['total'], 'tier' => $r['tier'], 'dimensions' => $dims, 'computed_at' => now()]);
    }

    /** Keeps each person's newest snapshots only (config reputation.keep_snapshots, default 60); the history is for explaining a number, not for ever. @return int rows removed */
    public function pruneSnapshots(?int $keep = null): int
    {
        $keep ??= (int) config('reputation.keep_snapshots', 60);
        $removed = 0;

        $heavy = ReputationSnapshot::query()->select('user_id')->groupBy('user_id')->havingRaw('count(*) > ?', [$keep])->pluck('user_id');

        foreach ($heavy as $uid) {
            $cutoff = ReputationSnapshot::where('user_id', $uid)->orderByDesc('id')->skip($keep)->take(1)->value('id');

            if ($cutoff !== null) {
                $removed += ReputationSnapshot::where('user_id', $uid)->where('id', '<=', $cutoff)->delete();
            }
        }

        return $removed;
    }

    /** Opting out removes everything stored about the person's score, including appeals; opting back in starts fresh. */
    public function forget(User $user): void
    {
        ReputationAppeal::where('user_id', $user->id)->delete();
        ReputationSnapshot::where('user_id', $user->id)->delete();
        // Groups flagged as boosting each other that include this person go too: nothing about them is kept once they switch the score off.
        ReputationRingFlag::all()->filter(fn ($f) => in_array($user->id, (array) $f->member_ids, true))->each->delete();
    }

    /** @return array<string, mixed> everything stored about one person's score, for them to keep (POPIA section 23) */
    public function export(User $user): array
    {
        return [
            'person' => $user->email,
            'exported_at' => now()->toIso8601String(),
            'snapshots' => ReputationSnapshot::where('user_id', $user->id)->orderBy('id')->get()->map(fn ($s) => ['computed_at' => $s->computed_at?->toIso8601String(), 'rules_version' => $s->rules_version, 'total' => $s->total, 'tier' => $s->tier, 'dimensions' => $s->dimensions])->all(),
            'ring_flags' => ReputationRingFlag::all()->filter(fn ($f) => in_array($user->id, (array) $f->member_ids, true))->map(fn ($f) => [
                'kind' => $f->kind, 'members_in_group' => count((array) $f->member_ids), 'status' => $f->status, 'detected_at' => $f->detected_at?->toIso8601String(),
                'reviewed_at' => $f->reviewed_at?->toIso8601String(), 'review_note' => $f->review_note,
            ])->values()->all(),
            'appeals' => ReputationAppeal::where('user_id', $user->id)->orderBy('id')->get()->map(fn ($a) => ['sent_at' => $a->created_at?->toIso8601String(), 'message' => $a->message, 'status' => $a->status, 'resolution_note' => $a->resolution_note])->all(),
        ];
    }

    /** @return array{0: string, 1: string, 2: ?array{name: string, needed: int, remaining: int}} */
    private function tierFor(int $total): array
    {
        $tiers = collect(config('reputation.tiers'))->sortBy('from');
        $current = $tiers->filter(fn ($t) => $total >= $t['from'])->keys()->last() ?? $tiers->keys()->first();
        $next = $tiers->first(fn ($t) => $t['from'] > $total);

        return [$current, $tiers[$current]['name'], $next ? ['name' => $next['name'], 'needed' => (int) $next['from'], 'remaining' => (int) $next['from'] - $total] : null];
    }

    /** @return array<string, float|int> raw counts per rule, before caps */
    private function evidence(User $user): array
    {
        $counts = $this->achievements->counts($user);
        $publicQuestionIds = Questions::withoutGlobalScopes()->where('visibility', Questions::VISIBILITY_PUBLIC)->whereNull('hidden_at')->select('id');

        $accepted = Answer::withoutGlobalScope('moderation-visibility')
            ->where('user_id', $user->id)->where('status', Answer::PUBLISHED)->whereNull('hidden_at')->where('is_self_answer', false)
            ->whereNotNull('accepted_at')->whereNotNull('accepted_by')->where('accepted_by', '!=', $user->id)
            ->whereIn('question_id', $publicQuestionIds)->orderBy('accepted_at')->get(['id', 'question_id', 'accepted_by']);
        $askers = Questions::withoutGlobalScopes()->whereIn('id', $accepted->pluck('question_id'))->pluck('user_id', 'id');
        $decay = config('reputation.points.answer_accepted.decay', [1.0]);
        // People whose answers this person accepted. If one of them also accepted this person's answers, the two are vouching for
        // each other, which is exactly what a ring of accounts does, so that pair's acceptances count for less (config reciprocal_factor).
        $reciprocal = Answer::withoutGlobalScope('moderation-visibility')->where('accepted_by', $user->id)->whereNotNull('accepted_at')
            ->where('user_id', '!=', $user->id)->pluck('user_id')->unique()->flip();
        $factor = (float) config('reputation.points.answer_accepted.reciprocal_factor', 0.5);
        // Accounts a moderator has not cleared as a ring with this person (open or confirmed flags; dismissed ones have no effect). An acceptance
        // from one of them counts ring `discount_factor` times as much. It is never stacked with the reciprocal factor: the lower one applies.
        $ringMates = $this->ringMates($user);
        $ringFactor = (float) config('reputation.rings.discount_factor', 0.25);
        $seen = [];
        $acceptedWeighted = 0.0;
        foreach ($accepted as $a) {
            $asker = $askers[$a->question_id] ?? null;
            $n = $seen[$asker] = ($seen[$asker] ?? -1) + 1;
            $link = min($reciprocal->has($asker) ? $factor : 1.0, isset($ringMates[(int) $a->accepted_by]) ? $ringFactor : 1.0);
            $acceptedWeighted += ($decay[$n] ?? 0.0) * $link;
        }

        $topics = DB::table('question_topic')->whereIn('question_id', (clone $publicQuestionIds)->where('user_id', $user->id))->distinct()->count('topic_id');

        $ownAnswerIds = Answer::withoutGlobalScope('moderation-visibility')->where('user_id', $user->id)->pluck('id');
        $ownSolutionIds = Solutions::withoutGlobalScopes()->where('user_id', $user->id)->pluck('id');
        $adopted = LibraryEntry::whereNull('withdrawn_at')
            ->where(fn ($q) => $q->whereIn('answer_id', $ownAnswerIds)->orWhereIn('solution_id', $ownSolutionIds))->count();

        $hidden = Questions::withoutGlobalScopes()->where('user_id', $user->id)->whereNotNull('hidden_at')->count()
            + Answer::withoutGlobalScope('moderation-visibility')->where('user_id', $user->id)->whereNotNull('hidden_at')->count()
            + Solutions::withoutGlobalScopes()->where('user_id', $user->id)->whereNotNull('hidden_at')->count();

        $host = $this->seminars->hostStats($user);
        $weeks = $this->activeWeeks($user, (int) config('reputation.points.active_week.window_weeks', 12));

        return [
            'question_asked' => $counts['questions'],
            'topic_explored' => $topics,
            'seminar_attended' => SeminarRegistration::where('user_id', $user->id)->whereNotNull('attended_at')->count(),
            'answer_given' => $counts['answers'],
            'solution_written' => Solutions::withoutGlobalScopes()->where('user_id', $user->id)->where('visibility', Solutions::VISIBILITY_PUBLIC)->whereNull('hidden_at')->count(),
            'answer_accepted' => $acceptedWeighted,
            'solution_verified' => $counts['verified'],
            'library_adoption' => $adopted,
            'active_week' => $weeks,
            'expertise_area' => count($this->achievements->expertise($user)),
            'vouched_for' => $counts['accepted'] + $counts['verified'],
            'seminar_host_rated_well' => ($host['average'] !== null && $host['average'] >= (float) config('reputation.points.seminar_host_rated_well.min_average', 4.0)) ? 1 : 0,
            'moderator_hid_work' => $hidden,
        ];
    }

    /**
     * The other members of every open or confirmed ring this person is in, as a set of user ids. Empty when ring checks are off.
     *
     * @return array<int, true>
     */
    /** The open and confirmed flags, read once a minute at most: the nightly run asked for them again for every person. */
    private function effectiveFlags(): Collection
    {
        if ($this->flagsLoadedAt === null || $this->flagsVersion !== self::$flagsGeneration || $this->flagsLoadedAt->diffInSeconds(now()) > 60) {
            $this->flagsVersion = self::$flagsGeneration;
            $this->flags = ReputationRingFlag::effective()->get(['member_ids']);
            $this->flagsLoadedAt = now();
        }

        return $this->flags;
    }

    private ?Carbon $flagsLoadedAt = null;

    private int $flagsVersion = 0;

    /** Bumped whenever a ring flag is saved or deleted, so a cached list is never used after a moderator's decision. */
    private static int $flagsGeneration = 1;

    public static function ringFlagsChanged(): void
    {
        self::$flagsGeneration++;
    }

    private ?Collection $flags = null;

    private function ringMates(User $user): array
    {
        if (! config('reputation.rings.enabled', true)) {
            return [];
        }

        $mates = [];
        foreach ($this->effectiveFlags() as $flag) {
            $members = array_map('intval', $flag->member_ids);

            if (in_array((int) $user->id, $members, true)) {
                foreach ($members as $m) {
                    $m === (int) $user->id || $mates[$m] = true;
                }
            }
        }

        return $mates;
    }

    private function activeWeeks(User $user, int $window): int
    {
        $since = now()->subWeeks($window);
        $publicQuestionIds = Questions::withoutGlobalScopes()->where('visibility', Questions::VISIBILITY_PUBLIC)->whereNull('hidden_at')->select('id');
        $dates = Questions::withoutGlobalScopes()->where('user_id', $user->id)->where('visibility', Questions::VISIBILITY_PUBLIC)->whereNull('hidden_at')->where('created_at', '>=', $since)->pluck('created_at')
            ->merge(Answer::withoutGlobalScope('moderation-visibility')->where('user_id', $user->id)->where('status', Answer::PUBLISHED)->whereNull('hidden_at')->where('is_self_answer', false)->whereIn('question_id', $publicQuestionIds)->where('created_at', '>=', $since)->pluck('created_at'))
            ->merge(Solutions::withoutGlobalScopes()->where('user_id', $user->id)->where('visibility', Solutions::VISIBILITY_PUBLIC)->whereNull('hidden_at')->where('created_at', '>=', $since)->pluck('created_at'));

        return $dates->map(fn ($d) => Carbon::parse($d)->format('o-W'))->unique()->count();
    }
}
