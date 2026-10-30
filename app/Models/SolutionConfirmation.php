<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "I tried this and it works" (R-16): one person's statement that they implemented a solution in a real situation and
 * it worked for them. Deliberately not a like: the count of these rows is shown as implementation confirmations and
 * never mixed into any popularity number. The feedback fields are optional context, added later or never.
 *
 * @property int $id
 * @property int $solution_id
 * @property int $user_id
 * @property string|null $business_industry
 * @property string|null $applied_part
 * @property string|null $results
 * @property string|null $timeframe
 * @property bool|null $recommend
 */
class SolutionConfirmation extends Model
{
    protected $fillable = ['solution_id', 'user_id', 'business_industry', 'applied_part', 'results', 'timeframe', 'recommend'];

    protected function casts(): array
    {
        return ['recommend' => 'boolean'];
    }

    public function solution(): BelongsTo
    {
        return $this->belongsTo(Solutions::class, 'solution_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
