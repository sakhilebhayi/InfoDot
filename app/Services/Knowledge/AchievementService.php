<?php

namespace App\Services\Knowledge;

use App\Models\Answer;
use App\Models\Questions;
use App\Models\Solutions;
use App\Models\User;
use App\Models\UserAchievement;
use App\Support\Knowledge\AchievementEvaluator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Keeps a person's milestones equal to what their current work earns. `sync()` is the only writer: it counts what
 * qualifies right now, awards what is newly met, takes back what is no longer met and gives back what is met again.
 * Calling it twice changes nothing the second time, so any event may call it and a backfill may call it for everyone.
 */
class AchievementService
{
    public function __construct(private readonly AchievementEvaluator $evaluator) {}

    /**
     * What qualifies right now. Public work only, never hidden, never help given to yourself; see config/achievements.php.
     * Global scopes are lifted on purpose: the rules are written out here, not left to whoever happens to be signed in.
     *
     * @return array{questions: int, answers: int, accepted: int, verified: int}
     */
    public function counts(User $user): array
    {
        $visibleQuestions = Questions::withoutGlobalScopes()
            ->where('visibility', Questions::VISIBILITY_PUBLIC)->whereNull('hidden_at');

        $answers = fn () => Answer::withoutGlobalScope('moderation-visibility')
            ->where('user_id', $user->id)->where('status', Answer::PUBLISHED)->whereNull('hidden_at')
            ->where('is_self_answer', false)
            ->whereIn('question_id', (clone $visibleQuestions)->select('id'));

        return [
            'questions' => (clone $visibleQuestions)->where('user_id', $user->id)->count(),
            'answers' => $answers()->count(),
            'accepted' => $answers()->whereNotNull('accepted_at')->whereNotNull('accepted_by')->where('accepted_by', '!=', $user->id)->count(),
            'verified' => $this->verifiedSolutions($user)->count(),
        ];
    }

    private function verifiedSolutions(User $user)
    {
        return Solutions::withoutGlobalScopes()
            ->where('user_id', $user->id)->where('visibility', Solutions::VISIBILITY_PUBLIC)->whereNull('hidden_at')
            ->whereNotNull('verified_at')->whereNotNull('verified_by')->where('verified_by', '!=', $user->id);
    }

    /** @return array{awarded: int, revoked: int, restored: int} */
    public function sync(User $user): array
    {
        $met = $this->evaluator->met($this->counts($user));
        $rows = UserAchievement::where('user_id', $user->id)->get()->keyBy('key');
        $version = $this->evaluator->version();
        $changes = ['awarded' => 0, 'revoked' => 0, 'restored' => 0];

        foreach ($met as $key => $isMet) {
            /** @var UserAchievement|null $row */
            $row = $rows->get($key);

            if ($isMet && $row === null) {
                try {
                    UserAchievement::create(['user_id' => $user->id, 'key' => $key, 'earned_at' => now(), 'rule_version' => $version]);
                    $changes['awarded']++;
                } catch (UniqueConstraintViolationException) {
                    // Another process awarded it a moment ago: it is there, which is all this wanted.
                }
            } elseif ($isMet && $row->revoked_at !== null) {
                $row->forceFill(['revoked_at' => null, 'rule_version' => $version])->save(); // earned_at stays the first time
                $changes['restored']++;
            } elseif (! $isMet && $row !== null && $row->revoked_at === null) {
                $row->forceFill(['revoked_at' => now()])->save();
                $changes['revoked']++;
            }
        }

        return $changes;
    }

    /**
     * What the profile shows. The owner sees milestones reached and how far the next ones are; anyone else sees reached
     * milestones and the public portfolio, and only when the owner chose to share. Null means "show nothing at all".
     *
     * @return array<string, mixed>|null
     */
    public function profileFor(User $user, bool $ownerView): ?array
    {
        if (! $ownerView && ! $user->sharesAchievements()) {
            return null;
        }

        $catalogue = $this->evaluator->catalogue();
        $earned = UserAchievement::where('user_id', $user->id)->whereNull('revoked_at')->orderBy('earned_at')->orderBy('id')->get()
            ->filter(fn (UserAchievement $a) => isset($catalogue[$a->key]))
            ->map(fn (UserAchievement $a) => ['key' => $a->key, 'name' => $catalogue[$a->key]['name'], 'meaning' => $catalogue[$a->key]['meaning'], 'earned_at' => $a->earned_at])
            ->values();

        $profile = ['earned' => $earned, 'shared' => $user->sharesAchievements(), 'portfolio' => $this->portfolio($user), 'expertise' => $this->expertise($user)];

        if ($ownerView) {
            $labels = config('achievements.metrics', []);
            $profile['next'] = collect($this->evaluator->next($this->counts($user)))
                ->map(fn (array $n) => $n + ['label' => $labels[$n['metric']]['label'] ?? $n['metric']])->all();
        }

        return $profile;
    }

    /**
     * Where a person's work is concentrated, from actual contribution history and nothing they declared: each topic with at
     * least `achievements.expertise.min_work` qualifying pieces of work filed under it, most first, topic name as tie-break.
     * The same work rules as the milestones (public, not hidden, accepted or verified by somebody else). Topics that were
     * switched off still label old work, so they still count. An item with several topics counts once under each.
     *
     * @return list<array{slug: string, name: string, accepted: int, verified: int, work: int}>
     */
    public function expertise(User $user): array
    {
        $min = max(1, (int) config('achievements.expertise.min_work', 2));
        $max = max(1, (int) config('achievements.expertise.max_areas', 5));

        $publicQuestions = Questions::withoutGlobalScopes()->where('visibility', Questions::VISIBILITY_PUBLIC)->whereNull('hidden_at')->select('id');

        $acceptedIds = Answer::withoutGlobalScope('moderation-visibility')
            ->where('user_id', $user->id)->where('status', Answer::PUBLISHED)->whereNull('hidden_at')->where('is_self_answer', false)
            ->whereNotNull('accepted_at')->whereNotNull('accepted_by')->where('accepted_by', '!=', $user->id)
            ->whereIn('question_id', $publicQuestions)->get(['id', 'question_id']);

        $accepted = $acceptedIds->isEmpty() ? collect() : DB::table('question_topic')
            ->whereIn('question_id', $acceptedIds->pluck('question_id')->unique()->all())
            ->get(['question_id', 'topic_id']);

        $counts = []; // topic id => [accepted, verified]
        foreach ($acceptedIds as $answer) {
            foreach ($accepted->where('question_id', $answer->question_id) as $link) {
                $counts[$link->topic_id][0] = ($counts[$link->topic_id][0] ?? 0) + 1;
            }
        }

        $verifiedIds = $this->verifiedSolutions($user)->pluck('id')->all();
        if ($verifiedIds !== []) {
            foreach (DB::table('solution_topic')->whereIn('solution_id', $verifiedIds)->get(['solution_id', 'topic_id']) as $link) {
                $counts[$link->topic_id][1] = ($counts[$link->topic_id][1] ?? 0) + 1;
            }
        }

        if ($counts === []) {
            return [];
        }

        $topics = DB::table('topics')->whereIn('id', array_keys($counts))->get(['id', 'slug', 'name'])->keyBy('id');

        return collect($counts)
            ->map(fn (array $c, $topicId) => [
                'slug' => (string) $topics[$topicId]->slug, 'name' => (string) $topics[$topicId]->name,
                'accepted' => (int) ($c[0] ?? 0), 'verified' => (int) ($c[1] ?? 0), 'work' => (int) (($c[0] ?? 0) + ($c[1] ?? 0)),
            ])
            ->filter(fn (array $t) => $t['work'] >= $min)
            ->sort(fn (array $a, array $b) => [$b['work'], $a['name']] <=> [$a['work'], $b['name']])
            ->take($max)->values()->all();
    }

    /**
     * The work a person can show: public, not hidden, accepted (by someone else) or verified (by someone else). Titles and
     * links only: no points, no moderation, nothing from company-only or private questions.
     *
     * @return array{accepted: Collection<int, array{title: string, url: string}>, verified: Collection<int, array{title: string, url: string}>, accepted_total: int, verified_total: int}
     */
    public function portfolio(User $user, int $limit = 10): array
    {
        $accepted = Answer::withoutGlobalScope('moderation-visibility')
            ->where('user_id', $user->id)->where('status', Answer::PUBLISHED)->whereNull('hidden_at')->where('is_self_answer', false)
            ->whereNotNull('accepted_at')->whereNotNull('accepted_by')->where('accepted_by', '!=', $user->id)
            ->whereIn('question_id', Questions::withoutGlobalScopes()->where('visibility', Questions::VISIBILITY_PUBLIC)->whereNull('hidden_at')->select('id'));

        $verified = $this->verifiedSolutions($user);

        return [
            'accepted_total' => (clone $accepted)->count(),
            'verified_total' => (clone $verified)->count(),
            'accepted' => $accepted->with('question:id,question')->orderByDesc('accepted_at')->limit($limit)->get()
                ->map(fn (Answer $a) => ['title' => (string) $a->question->question, 'url' => route('questions.view', ['qid' => $a->question_id])]),
            'verified' => $verified->orderByDesc('verified_at')->limit($limit)->get(['id', 'solution_title'])
                ->map(fn (Solutions $s) => ['title' => (string) $s->solution_title, 'url' => route('solutions.view', ['id' => $s->id])]),
        ];
    }
}
