<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A person's private list of businesses (R-33 §8): suppliers, contractors, businesses to contact
 * later. Nobody else sees it, including the businesses in it.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 */
class BusinessCollection extends Model
{
    /** Names offered when someone makes their first collection. */
    public const SUGGESTED = ['Suppliers', 'Potential partners', 'Service providers', 'Contractors', 'To contact later'];

    protected $fillable = ['name'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<BusinessCollectionItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(BusinessCollectionItem::class)->latest('id');
    }
}
