<?php

namespace App\Models;

use App\Support\OwnerCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;
use Laravel\Jetstream\Events\TeamCreated;
use Laravel\Jetstream\Events\TeamDeleted;
use Laravel\Jetstream\Events\TeamUpdated;
use Laravel\Jetstream\Team as JetstreamTeam;

class Team extends JetstreamTeam
{
    use HasFactory;

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'personal_team' => 'boolean',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var string[]
     */
    protected $fillable = [
        'name',
        'personal_team',
    ];

    /**
     * The event map for the model.
     *
     * @var array
     */
    protected $dispatchesEvents = [
        'created' => TeamCreated::class,
        'updated' => TeamUpdated::class,
        'deleted' => TeamDeleted::class,
    ];

    public function licence()
    {
        return $this->hasOne(TeamLicence::class);
    }

    public static function booted()
    {
        static::creating(function ($team) {
            $team->uuid ??= (string) Str::uuid();
            $team->code ??= OwnerCode::for((string) $team->name, fn (string $c) => static::where('code', $c)->exists());
        });

        static::created(function ($team) {
            $object = $team->objects()->make(['parent_id' => null]);
            $object->objectable()->associate($team->folders()->create(['name' => $team->name]));
            $object->save();
        });
    }

    public function objects()
    {
        return $this->hasMany(Obj::class);
    }

    public function files()
    {
        return $this->hasMany(File::class);
    }

    public function folders()
    {
        return $this->hasMany(Folder::class);
    }
}
