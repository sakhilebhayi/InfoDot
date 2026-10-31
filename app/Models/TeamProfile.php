<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The public business profile of a company (R-29 Part A). Everything here may be shown to any
 * signed-in person — sensitive verification material never enters this table.
 *
 * @property int $id
 * @property int $team_id
 * @property string|null $trading_name
 * @property string|null $description
 * @property string|null $industry
 * @property string|null $category
 * @property string|null $products_services
 * @property string|null $location
 * @property string|null $regions
 * @property string|null $website
 * @property string|null $contact_email
 * @property string|null $contact_phone
 * @property string|null $registration_number
 * @property string|null $registration_country
 * @property Carbon|null $established_on
 * @property string|null $size
 * @property array|null $links
 */
class TeamProfile extends Model
{
    /** The social/professional link slots the profile form offers. */
    public const LINK_KEYS = ['linkedin', 'facebook', 'instagram', 'x', 'youtube'];

    protected $fillable = [
        'trading_name', 'description', 'industry', 'category', 'products_services',
        'location', 'regions', 'website', 'contact_email', 'contact_phone',
        'registration_number', 'registration_country', 'established_on', 'size', 'links',
    ];

    protected function casts(): array
    {
        return [
            'established_on' => 'date',
            'links' => 'array',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** True once the profile says anything at all — the public page hides empty sections. */
    public function hasContent(): bool
    {
        return collect($this->only(array_diff($this->fillable, ['links'])))->filter()->isNotEmpty()
            || ! empty(array_filter($this->links ?? []));
    }
}
