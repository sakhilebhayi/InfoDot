<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A directory category (R-33 §2), managed centrally so the directory stays consistent.
 *
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property int $sort
 * @property bool $active
 */
class BusinessCategory extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'sort' => 'integer'];
    }

    /** @return BelongsToMany<Team, $this> */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class);
    }
}
