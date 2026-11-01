<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Where the business operates from (R-33 §4): an office, branch, workshop or store. The street
 * address shows only when the business chooses (`show_address`); otherwise town and province.
 *
 * @property int $id
 * @property int $team_id
 * @property string $label
 * @property string|null $address
 * @property string $city
 * @property string|null $province
 * @property string|null $postal_code
 * @property string|null $country
 * @property bool $show_address
 * @property int $sort
 */
class BusinessLocation extends Model
{
    protected $fillable = ['label', 'address', 'city', 'province', 'postal_code', 'country', 'show_address'];

    protected function casts(): array
    {
        return ['show_address' => 'boolean', 'sort' => 'integer'];
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** What the public sees: the full address when published, else town and province. */
    public function publicLine(): string
    {
        $place = collect([$this->city, $this->province])->filter()->implode(', ');

        return $this->show_address && $this->address
            ? collect([$this->address, $place, $this->postal_code])->filter()->implode(', ')
            : $place;
    }

    /** A map search for this place: the published address, or only the town when it is not. */
    public function mapUrl(string $businessName): string
    {
        $query = $this->show_address && $this->address ? $this->publicLine() : $businessName.', '.$this->publicLine();

        return 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($query);
    }
}
