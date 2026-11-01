<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A relationship an account claims with a verified business — branch, subsidiary, franchise,
 * distributor or authorised representative account (R-29 §19). Only the verified business's
 * owner or an administrator confirms it; once confirmed, shared names and branding between the
 * two are expected, not suspicious.
 *
 * @property int $id
 * @property int $team_id
 * @property int $parent_team_id
 * @property string $kind
 * @property string $status
 * @property string|null $note
 * @property int|null $requested_by
 * @property int|null $decided_by
 * @property Carbon|null $decided_at
 * @property-read Team $team
 * @property-read Team $parent
 */
class BusinessRelationship extends Model
{
    public const KINDS = [
        'branch' => 'Branch',
        'subsidiary' => 'Subsidiary',
        'franchise' => 'Franchise',
        'distributor' => 'Authorised distributor',
        'representative' => 'Authorised representative or staff account',
    ];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<Team, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'parent_team_id');
    }
}
