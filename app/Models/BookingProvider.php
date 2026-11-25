<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A person in the business who offers appointments (R-63). Customers see `public_name`, a label the business chooses, never the person's account.
 * A provider with no working hours of their own works the business's hours; one with hours works only the part of them that falls inside the business's.
 *
 * @property int $id
 * @property int $team_id
 * @property int $user_id
 * @property string $public_name
 * @property bool $active
 */
class BookingProvider extends Model
{
    protected $fillable = ['user_id', 'public_name', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    /** @param  Builder<self>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('active', true);
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsToMany<AppointmentType, $this> */
    public function appointmentTypes(): BelongsToMany
    {
        return $this->belongsToMany(AppointmentType::class, 'appointment_type_provider');
    }

    /** @return HasMany<WorkingHour, $this> */
    public function workingHours(): HasMany
    {
        return $this->hasMany(WorkingHour::class);
    }
}
