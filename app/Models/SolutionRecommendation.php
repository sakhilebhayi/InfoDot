<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An existing solution offered with an answer (R-16): a link to the original, shown as a card under the answer. The
 * original keeps its authorship; recommending never copies it.
 *
 * @property int $id
 * @property int $answer_id
 * @property int $solution_id
 * @property int $recommender_id
 */
class SolutionRecommendation extends Model
{
    public $timestamps = false;

    protected $fillable = ['answer_id', 'solution_id', 'recommender_id', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function answer(): BelongsTo
    {
        return $this->belongsTo(Answer::class);
    }

    public function solution(): BelongsTo
    {
        // Unscoped: whether the viewer may see it is decided where the card is drawn.
        return $this->belongsTo(Solutions::class, 'solution_id')->withoutGlobalScopes();
    }

    public function recommender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recommender_id');
    }
}
