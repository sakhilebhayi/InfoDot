<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One version of a solution's content (R-16): what the whole procedure said at version N, who originally wrote it and
 * who contributed the change that produced this version. Approved improvements append versions; nothing is ever
 * silently overwritten, and ownership never transfers.
 *
 * @property int $id
 * @property int $solution_id
 * @property int $version
 * @property string $solution_title
 * @property string|null $solution_description
 * @property array $steps
 * @property int $author_id
 * @property int|null $contributor_id
 * @property string|null $summary
 */
class SolutionVersion extends Model
{
    public $timestamps = false;

    protected $fillable = ['solution_id', 'version', 'solution_title', 'solution_description', 'steps', 'author_id', 'contributor_id', 'summary', 'created_at'];

    protected function casts(): array
    {
        return ['steps' => 'array', 'created_at' => 'datetime'];
    }

    public function contributor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'contributor_id');
    }
}
