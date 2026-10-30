<?php

namespace App\Services\Knowledge;

use App\Models\Questions;
use App\Models\SolutionConfirmation;
use App\Models\Solutions;
use App\Models\User;
use App\Support\Reference;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Finds existing questions and solutions that look like what someone is about to post (R-16 phase 3). Two stages:
 * the TNT search index proposes public candidates (it ranks words and their forms, which is as much semantics as this
 * host honestly has — there is no embedding service), a LIKE pass adds the person's own company content (which is not
 * in the index), then everything is scored by overlap of significant terms and labelled. Guidance, never a gate: the
 * caller shows the list; publishing is never blocked.
 */
class SimilarContent
{
    /** Overlap at or above this reads as "very similar" — probably the same problem. */
    public const VERY_SIMILAR = 0.6;

    /** Overlap at or above this reads as "related" — worth a look before posting. */
    public const RELATED = 0.28;

    private const STOPWORDS = ['the', 'and', 'for', 'with', 'that', 'this', 'what', 'when', 'where', 'which', 'how', 'why', 'can', 'cant', 'cannot',
        'does', 'doesnt', 'dont', 'do', 'is', 'are', 'was', 'were', 'be', 'been', 'have', 'has', 'had', 'will', 'would', 'should', 'could',
        'you', 'your', 'our', 'their', 'they', 'them', 'there', 'here', 'from', 'into', 'out', 'about', 'before', 'after', 'not', 'but',
        'business', 'company', 'small', 'help', 'need', 'want', 'get', 'make', 'way', 'best', 'please', 'anyone', 'someone', 'start', 'stop'];

    /**
     * @return list<array{kind: string, id: int, title: string, excerpt: string, author: string, date: string, url: string, label: string, ref: string, tried: int}>
     */
    public function find(User $viewer, string $kind, string $text, int $excludeId = 0): array
    {
        $terms = $this->terms($text);

        if (count($terms) < 2) {
            return [];
        }

        $questions = $this->candidates(Questions::class, $text, $terms, $viewer);
        $solutions = $this->candidates(Solutions::class, $text, $terms, $viewer);

        $scored = [];

        foreach ($questions as $question) {
            if ($kind === 'question' && $question->id === $excludeId) {
                continue;
            }
            $score = $this->overlap($terms, (string) $question->question.' '.$question->description);
            if ($score >= self::RELATED) {
                $scored[] = ['score' => $score, 'model' => $question, 'kind' => 'question'];
            }
        }

        foreach ($solutions as $solution) {
            if ($kind === 'solution' && $solution->id === $excludeId) {
                continue;
            }
            $score = $this->overlap($terms, (string) $solution->solution_title.' '.$solution->solution_description.' '.$solution->tags);
            if ($score >= self::RELATED) {
                $scored[] = ['score' => $score, 'model' => $solution, 'kind' => 'solution'];
            }
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);
        $scored = array_slice($scored, 0, 5);

        $tried = SolutionConfirmation::whereIn('solution_id', collect($scored)->where('kind', 'solution')->pluck('model.id'))
            ->selectRaw('solution_id, count(*) as n')->groupBy('solution_id')->pluck('n', 'solution_id');

        return array_map(function ($row) use ($tried) {
            $model = $row['model'];
            $isQuestion = $row['kind'] === 'question';

            return [
                'kind' => $row['kind'],
                'id' => (int) $model->id,
                'title' => $isQuestion ? (string) $model->question : (string) $model->solution_title,
                'excerpt' => Str::limit((string) ($isQuestion ? $model->description : $model->solution_description), 120),
                'author' => $model->user->name ?? 'Former member',
                'date' => $model->created_at?->format('j M Y') ?? '',
                'url' => $isQuestion ? route('questions.view', ['qid' => $model->id]) : route('solutions.view', ['id' => $model->id]),
                'label' => $row['score'] >= self::VERY_SIMILAR ? 'very-similar' : 'related',
                'ref' => Reference::for($model),
                'tried' => (int) ($tried[$model->id] ?? 0),
            ];
        }, $scored);
    }

    /**
     * Public candidates from the search index (ranked), plus the person's own company's content the index never holds.
     * Everything is re-read through the visibility scope, so nothing leaks whatever the index answers.
     *
     * @param  class-string<Questions>|class-string<Solutions>  $model
     * @param  list<string>  $terms
     */
    private function candidates(string $model, string $text, array $terms, User $viewer): Collection
    {
        $ids = [];

        try {
            $ids = $model::search(Str::limit($text, 200, ''))->take(15)->keys()->all();
        } catch (\Throwable) {
            // The index may be missing or cold (fresh install); the LIKE pass below still works.
        }

        $likeIds = $model::query()
            ->where(function ($query) use ($terms, $model) {
                [$title, $body] = $model === Questions::class ? ['question', 'description'] : ['solution_title', 'solution_description'];
                foreach (array_slice($terms, 0, 8) as $term) {
                    $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $term).'%';
                    $query->orWhere($title, 'like', $like)->orWhere($body, 'like', $like);
                }
            })
            ->latest()->limit(15)->pluck('id')->all();

        // The scoped read is what decides visibility; index hits the person may not see are simply not found.
        return $model::with('user')->whereIn('id', array_unique(array_merge($ids, $likeIds)))->get();
    }

    /** @return list<string> the words that carry meaning, lowercased, deduplicated, capped */
    public function terms(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $terms = [];
        foreach ($words as $word) {
            if (mb_strlen($word) < 3 || in_array($word, self::STOPWORDS, true)) {
                continue;
            }
            $terms[$word] = true;
        }

        return array_slice(array_keys($terms), 0, 14);
    }

    /** How much of what the person wrote already appears in the candidate: matched terms over the draft's terms. */
    public function overlap(array $terms, string $candidateText): float
    {
        if ($terms === []) {
            return 0.0;
        }

        $candidate = array_flip($this->termsUncapped($candidateText));
        $matched = count(array_filter($terms, fn ($term) => isset($candidate[$term])));

        return $matched / count($terms);
    }

    /** @return list<string> */
    private function termsUncapped(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $terms = [];
        foreach ($words as $word) {
            if (mb_strlen($word) >= 3 && ! in_array($word, self::STOPWORDS, true)) {
                $terms[$word] = true;
            }
        }

        return array_keys($terms);
    }
}
