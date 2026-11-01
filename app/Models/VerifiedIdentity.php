<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The protected identity of a verified business (R-29 §14): normalised names, a hash of its
 * registration number, its web domains and fingerprints of its logo and cover. Active while
 * the business is verified; new accounts are compared against it.
 *
 * @property int $id
 * @property int $team_id
 * @property int $business_verification_id
 * @property bool $active
 * @property string|null $name_key
 * @property string|null $name_skeleton
 * @property string|null $trading_key
 * @property string|null $trading_skeleton
 * @property string|null $registration_hash
 * @property string|null $registration_country
 * @property string|null $website_host
 * @property string|null $email_domain
 * @property string|null $logo_hash
 * @property string|null $cover_hash
 * @property-read Team $team
 */
class VerifiedIdentity extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
