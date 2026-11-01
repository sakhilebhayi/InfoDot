<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A piece of work the business shows in its portfolio (R-33 §4). Its photographs are team
 * attachments with the role `project` and `meta.ref` pointing here.
 *
 * @property int $id
 * @property int $team_id
 * @property string $title
 * @property string|null $summary
 * @property int|null $year
 * @property string|null $location
 * @property int $sort
 */
class BusinessProject extends Model
{
    protected $fillable = ['title', 'summary', 'year', 'location'];

    protected function casts(): array
    {
        return ['year' => 'integer', 'sort' => 'integer'];
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
