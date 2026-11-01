<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something a business offers (R-33 §4), searchable on its own in the directory.
 *
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string $kind
 * @property string|null $description
 * @property string|null $price_note
 * @property int $sort
 */
class BusinessService extends Model
{
    /** A service is work done for the customer; a product is a thing they buy. */
    public const KINDS = ['service' => 'Service', 'product' => 'Product'];

    protected $fillable = ['name', 'kind', 'description', 'price_note'];

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
