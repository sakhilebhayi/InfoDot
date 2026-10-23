<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/**
 * One adopted answer or solution in the Verified library. Holds a reference to the source and who adopted it, never a
 * copy of its text: whatever is shown is read from the source at that moment, so hidden or changed content cannot appear
 * stale. A withdrawn entry stays as a row (its history matters); it is simply no longer listed.
 */
class LibraryEntry extends Model
{
    public const ANSWER = 'answer';

    public const SOLUTION = 'solution';

    protected $fillable = ['contribution_ref', 'kind', 'answer_id', 'solution_id', 'adopted_by', 'adopted_at', 'recommendation_event_id', 'withdrawn_at'];

    protected function casts(): array
    {
        return ['adopted_at' => 'datetime', 'withdrawn_at' => 'datetime'];
    }

    public function answer(): BelongsTo
    {
        // Unscoped on purpose: whether the source may be shown is decided by scopeShown(), the same for every viewer.
        return $this->belongsTo(Answer::class)->withoutGlobalScopes();
    }

    public function solution(): BelongsTo
    {
        return $this->belongsTo(Solutions::class)->withoutGlobalScopes();
    }

    public function adopter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'adopted_by');
    }

    public function isWithdrawn(): bool
    {
        return $this->withdrawn_at !== null;
    }

    /** The entry that has not been withdrawn (whatever state its source is in). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('library_entries.withdrawn_at');
    }

    /**
     * What the library page may list: not withdrawn, and the source still public, published and not hidden or deleted.
     * The same answer for every viewer, moderators and the author included: the library never shows an entry whose
     * source the community cannot read.
     */
    public function scopeShown(Builder $query): Builder
    {
        return $query->active()->where(function (Builder $q) {
            $q->where(function (Builder $a) {
                $a->whereNotNull('library_entries.answer_id')->whereExists(fn ($sub) => $sub->selectRaw('1')->from('answers')
                    ->join('questions', 'questions.id', '=', 'answers.question_id')
                    ->whereColumn('answers.id', 'library_entries.answer_id')
                    ->whereNull('answers.deleted_at')->whereNull('answers.hidden_at')->where('answers.status', Answer::PUBLISHED)
                    ->whereNull('questions.hidden_at')->where('questions.visibility', Questions::VISIBILITY_PUBLIC));
            })->orWhere(function (Builder $s) {
                $s->whereNotNull('library_entries.solution_id')->whereExists(fn ($sub) => $sub->selectRaw('1')->from('solutions')
                    ->whereColumn('solutions.id', 'library_entries.solution_id')
                    ->whereNull('solutions.hidden_at')->where('solutions.visibility', Solutions::VISIBILITY_PUBLIC));
            });
        });
    }

    /** Entries whose source is filed under this topic (an answer is filed where its question is). */
    public function scopeInTopic(Builder $query, int $topicId): Builder
    {
        return $query->where(function (Builder $q) use ($topicId) {
            $q->whereIn('library_entries.answer_id', DB::table('answers')->join('question_topic', 'question_topic.question_id', '=', 'answers.question_id')
                ->where('question_topic.topic_id', $topicId)->select('answers.id'))
                ->orWhereIn('library_entries.solution_id', DB::table('solution_topic')->where('topic_id', $topicId)->select('solution_id'));
        });
    }

    /** The source, whichever kind it is (null if it has been deleted). */
    public function source(): ?Model
    {
        return $this->kind === self::ANSWER ? $this->answer : $this->solution;
    }

    public static function refFor(string $kind, int $id): string
    {
        return 'infodot:'.$kind.':'.$id;
    }

    /** @return array{0: string, 1: int}|null kind and id of a library-eligible reference (answers and solutions only) */
    public static function parseRef(string $ref): ?array
    {
        if (! preg_match('/\Ainfodot:(answer|solution):([1-9]\d{0,17})\z/', trim($ref), $m)) {
            return null;
        }

        return [$m[1], (int) $m[2]];
    }
}
