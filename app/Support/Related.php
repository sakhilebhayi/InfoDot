<?php

namespace App\Support;

use App\Models\Questions;
use App\Models\Solutions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * "More like this": other things the person can already see that share the words that
 * matter in a title. Plain word matching, no ranking, so it is predictable. Visibility is
 * the models' own scope, so it can never suggest something the person may not open.
 */
final class Related
{
    private const STOP = ['what', 'when', 'where', 'which', 'with', 'from', 'that', 'this', 'have', 'does', 'into', 'your', 'about', 'there', 'their', 'should', 'would', 'could', 'while', 'after', 'before', 'between'];

    /** @return list<string> */
    public static function words(string $text, int $max = 6): array
    {
        return collect(preg_split('/[^\pL\pN]+/u', Str::lower($text)) ?: [])
            ->filter(fn ($w) => mb_strlen($w) >= 4 && ! in_array($w, self::STOP, true))
            ->unique()
            ->take($max)
            ->values()
            ->all();
    }

    /** @return Collection<int, Questions> */
    public static function questions(Questions $question, int $limit = 4): Collection
    {
        return self::match(Questions::query()->where('id', '!=', $question->id), ['question', 'description'], self::words($question->question), $limit);
    }

    /** @return Collection<int, Solutions> */
    public static function solutions(Solutions $solution, int $limit = 4): Collection
    {
        return self::match(Solutions::query()->where('id', '!=', $solution->id), ['solution_title', 'tags'], self::words($solution->solution_title.' '.$solution->tags), $limit);
    }

    private static function match(Builder $query, array $columns, array $words, int $limit): Collection
    {
        if ($words === []) {
            return $query->getModel()->newCollection();
        }

        return $query->where(function (Builder $q) use ($columns, $words) {
            foreach ($words as $word) {
                $like = '%'.addcslashes($word, '\\%_').'%';
                foreach ($columns as $column) {
                    $q->orWhere($column, 'like', $like);
                }
            }
        })->latest()->limit($limit)->get();
    }
}
