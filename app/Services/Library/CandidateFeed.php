<?php

namespace App\Services\Library;

use App\Models\Answer;
use App\Models\LibraryEntry;
use App\Models\Questions;
use App\Models\Solutions;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * What Dot.Brain may pull: public answers and solutions that someone other than their author has corroborated, as
 * references and facts only. Never a title, a body, an email or anything from a company. Brain keeps a cursor
 * (`since`) and asks "what changed after this?", so an item that is later hidden, deleted or un-accepted comes back
 * once as a row with `hidden` or `reversed` set, and Brain can withdraw its recommendation.
 *
 * Deterministic: rows are ordered by the moment they last changed (the later of the item's and its question's
 * `updated_at`), then answers before solutions, then id. Cheap: the "changed since" test is a range on an indexed
 * `updated_at`. A page is the first `limit` rows by that order; rows that share the last row's moment are included even
 * if that exceeds `limit` slightly, so a cursor never skips one. Nothing here reads the viewer: it is the same for all.
 *
 * What it cannot know: InfoDot keeps no history of who accepted an answer before, so an item that changed after it was
 * created and is no longer corroborated is reported as `reversed` whether or not it ever was. That is a withdrawal of
 * something Brain may never have seen, which is harmless. `edited_at` is always null (content cannot be edited yet) and
 * `reuse_count` is 0 (nothing counts reuse yet); `helpful_count` is the number of likes on a solution (answers have none).
 */
class CandidateFeed
{
    /**
     * @return array{items: list<array<string, mixed>>, next_since: string|null}
     */
    public function page(?Carbon $since, int $limit): array
    {
        $sinceSql = $since?->copy()->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s');

        $answers = $this->answers($sinceSql)->limit($limit)->get();
        $solutions = $this->solutions($sinceSql)->limit($limit)->get();

        if ($answers->count() >= $limit || $solutions->count() >= $limit) {
            // The page is cut: find the moment of its last row, then take every row up to and including that moment.
            $boundary = $answers->map(fn ($r) => $this->ordering($r, 'answer'))
                ->merge($solutions->map(fn ($r) => $this->ordering($r, 'solution')))
                ->sort()->values()->get($limit - 1);
            $boundary = substr((string) $boundary, 0, 19);

            $answers = $this->answers($sinceSql)->where(DB::raw($this->answerChangedAt()), '<=', $boundary)->get();
            $solutions = $this->solutions($sinceSql)->where(DB::raw($this->solutionChangedAt()), '<=', $boundary)->get();
        }

        $rows = $answers->map(fn ($r) => ['kind' => 'answer', 'row' => $r])
            ->merge($solutions->map(fn ($r) => ['kind' => 'solution', 'row' => $r]))
            ->sortBy(fn ($r) => $this->ordering($r['row'], $r['kind']))
            ->values();

        $topics = $this->topics($rows);
        $solutionIds = $solutions->pluck('id')->all();
        $helpful = $this->helpfulCounts($solutionIds);
        $tried = $this->triedCounts($solutionIds);
        $reused = $this->reuseCounts($solutionIds);

        $items = $rows->map(fn ($r) => $this->item($r['kind'], $r['row'], $topics, $helpful, $tried, $reused))->all();
        $last = $rows->last();

        return ['items' => $items, 'next_since' => $last ? $this->iso($last['row']->changed_at) : null];
    }

    private function answerChangedAt(): string
    {
        $a = 'COALESCE(answers.updated_at, answers.created_at)';
        $q = 'COALESCE(questions.updated_at, questions.created_at)';

        return "CASE WHEN {$a} >= {$q} THEN {$a} ELSE {$q} END";
    }

    private function solutionChangedAt(): string
    {
        return 'COALESCE(solutions.updated_at, solutions.created_at)';
    }

    /** Sort key: when it changed, then answers before solutions, then id (zero padded so ids sort as numbers). */
    private function ordering(object $row, string $kind): string
    {
        return substr((string) $row->changed_at, 0, 19).'|'.($kind === 'answer' ? '0' : '1').'|'.str_pad((string) $row->id, 18, '0', STR_PAD_LEFT);
    }

    private function answers(?string $since): Builder
    {
        $query = DB::table('answers')
            ->join('questions', 'questions.id', '=', 'answers.question_id')
            ->join('users', 'users.id', '=', 'answers.user_id')
            ->where('questions.visibility', Questions::VISIBILITY_PUBLIC)
            ->where(fn ($q) => $q
                ->where(fn ($c) => $c->whereNotNull('answers.accepted_at')->whereNotNull('answers.accepted_by')->whereColumn('answers.accepted_by', '<>', 'answers.user_id'))
                ->orWhereColumn('answers.updated_at', '>', 'answers.created_at'))
            ->selectRaw('answers.id, answers.question_id, answers.created_at, answers.accepted_at, answers.accepted_by, answers.user_id, answers.status, '
                .'answers.hidden_at, answers.deleted_at, questions.hidden_at as question_hidden_at, users.uuid as author_uuid, '.$this->answerChangedAt().' as changed_at')
            ->orderByRaw($this->answerChangedAt())
            ->orderBy('answers.id');

        if ($since !== null) {
            $query->whereIn('answers.id', DB::table('answers')->select('id')->where('updated_at', '>', $since)
                ->union(DB::table('answers')->join('questions', 'questions.id', '=', 'answers.question_id')->select('answers.id')->where('questions.updated_at', '>', $since)));
        }

        return $query;
    }

    private function solutions(?string $since): Builder
    {
        $query = DB::table('solutions')
            ->join('users', 'users.id', '=', 'solutions.user_id')
            ->where('solutions.visibility', Solutions::VISIBILITY_PUBLIC)
            ->where(fn ($q) => $q
                ->where(fn ($c) => $c->whereNotNull('solutions.verified_at')->whereNotNull('solutions.verified_by')->whereColumn('solutions.verified_by', '<>', 'solutions.user_id'))
                ->orWhereColumn('solutions.updated_at', '>', 'solutions.created_at'))
            ->selectRaw('solutions.id, solutions.created_at, solutions.updated_at, solutions.version, solutions.verified_at, solutions.verified_by, solutions.user_id, solutions.hidden_at, '
                .'users.uuid as author_uuid, '.$this->solutionChangedAt().' as changed_at')
            ->orderByRaw($this->solutionChangedAt())
            ->orderBy('solutions.id');

        if ($since !== null) {
            $query->where('solutions.updated_at', '>', $since);
        }

        return $query;
    }

    /** Topic slugs by kind and item id: an answer is filed where its question is. */
    private function topics($rows): array
    {
        $out = [];
        $answerRows = $rows->where('kind', 'answer');
        $solutionIds = $rows->where('kind', 'solution')->pluck('row.id')->all();

        if ($answerRows->isNotEmpty()) {
            $byQuestion = DB::table('question_topic')->join('topics', 'topics.id', '=', 'question_topic.topic_id')
                ->whereIn('question_topic.question_id', $answerRows->pluck('row.question_id')->unique()->all())
                ->orderBy('topics.sort_order')->orderBy('topics.id')
                ->get(['question_topic.question_id', 'topics.slug'])->groupBy('question_id');

            foreach ($answerRows as $r) {
                $out['answer'][$r['row']->id] = ($byQuestion[$r['row']->question_id] ?? collect())->pluck('slug')->values()->all();
            }
        }

        if ($solutionIds !== []) {
            $out['solution'] = DB::table('solution_topic')->join('topics', 'topics.id', '=', 'solution_topic.topic_id')
                ->whereIn('solution_topic.solution_id', $solutionIds)
                ->orderBy('topics.sort_order')->orderBy('topics.id')
                ->get(['solution_topic.solution_id', 'topics.slug'])->groupBy('solution_id')
                ->map(fn ($g) => $g->pluck('slug')->values()->all())->all();
        }

        return $out;
    }

    /** Likes on each solution by someone other than its author, still standing. @return array<int, int> */
    private function helpfulCounts(array $solutionIds): array
    {
        if ($solutionIds === []) {
            return [];
        }

        return DB::table('likes')->join('solutions', 'solutions.id', '=', 'likes.likable_id')
            ->where('likes.likable_type', (new Solutions)->getMorphClass())
            ->whereIn('likes.likable_id', $solutionIds)->whereNull('likes.deleted_at')
            ->whereColumn('likes.user_id', '<>', 'solutions.user_id')
            ->selectRaw('likes.likable_id as id, count(*) as n')->groupBy('likes.likable_id')
            ->pluck('n', 'id')->map(fn ($n) => (int) $n)->all();
    }

    /** @return array<int, int> implementation confirmations per solution (never the author's own; the UI already refuses those) */
    private function triedCounts(array $solutionIds): array
    {
        if ($solutionIds === []) {
            return [];
        }

        return DB::table('solution_confirmations')->whereIn('solution_id', $solutionIds)
            ->selectRaw('solution_id as id, count(*) as n')->groupBy('solution_id')
            ->pluck('n', 'id')->map(fn ($n) => (int) $n)->all();
    }

    /** @return array<int, int> how often the solution was recommended as an answer to a question */
    private function reuseCounts(array $solutionIds): array
    {
        if ($solutionIds === []) {
            return [];
        }

        return DB::table('solution_recommendations')->whereIn('solution_id', $solutionIds)
            ->selectRaw('solution_id as id, count(*) as n')->groupBy('solution_id')
            ->pluck('n', 'id')->map(fn ($n) => (int) $n)->all();
    }

    private function item(string $kind, object $row, array $topics, array $helpful, array $tried = [], array $reused = []): array
    {
        if ($kind === 'answer') {
            $independent = $row->accepted_at !== null && $row->accepted_by !== null && (int) $row->accepted_by !== (int) $row->user_id;
            $hidden = $row->deleted_at !== null || $row->hidden_at !== null || $row->status !== Answer::PUBLISHED || $row->question_hidden_at !== null;
            $url = route('questions.view', ['qid' => $row->question_id]).'#answer-'.$row->id;
        } else {
            $independent = $row->verified_at !== null && $row->verified_by !== null && (int) $row->verified_by !== (int) $row->user_id;
            $hidden = $row->hidden_at !== null;
            $url = route('solutions.view', ['id' => $row->id]);
        }

        return [
            'contribution_ref' => LibraryEntry::refFor($kind, (int) $row->id),
            'kind' => $kind,
            'topics' => $topics[$kind][$row->id] ?? [],
            'url' => $url,
            'author_uuid' => $row->author_uuid,
            'created_at' => $this->iso($row->created_at),
            // R-16: an approved improvement bumps the version, so Brain can see corroborated content changed and when.
            'edited_at' => $kind === 'solution' && (int) ($row->version ?? 1) > 1 ? $this->iso($row->updated_at) : null,
            'accepted_by_independent' => $kind === 'answer' && $independent,
            'accepted_at' => $kind === 'answer' && $independent ? $this->iso($row->accepted_at) : null,
            'verified_by_independent' => $kind === 'solution' && $independent,
            'verified_at' => $kind === 'solution' && $independent ? $this->iso($row->verified_at) : null,
            'helpful_count' => $kind === 'solution' ? ($helpful[$row->id] ?? 0) : 0,
            // R-16: implementation confirmations ("I tried this and it works") and in-answer recommendations are real
            // reuse signals now, kept apart from likes on purpose.
            'tried_count' => $kind === 'solution' ? ($tried[$row->id] ?? 0) : 0,
            'reuse_count' => $kind === 'solution' ? ($reused[$row->id] ?? 0) : 0,
            'hidden' => $hidden,
            'reversed' => ! $independent,
        ];
    }

    private function iso(?string $value): ?string
    {
        return $value === null ? null : Carbon::parse($value, config('app.timezone'))->utc()->format('Y-m-d\TH:i:s\Z');
    }
}
