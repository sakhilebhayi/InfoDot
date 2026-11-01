<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $business_collection_id
 * @property int $team_id
 * @property string|null $note
 */
class BusinessCollectionItem extends Model
{
    protected $fillable = ['team_id', 'note'];

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<BusinessCollection, $this> */
    public function collection(): BelongsTo
    {
        return $this->belongsTo(BusinessCollection::class, 'business_collection_id');
    }
}
