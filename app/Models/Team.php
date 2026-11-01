<?php

namespace App\Models;

use App\Services\Identity\IdentityProtection;
use App\Services\Verification\VerificationService;
use App\Support\OwnerCode;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use Laravel\Jetstream\Events\TeamCreated;
use Laravel\Jetstream\Events\TeamDeleted;
use Laravel\Jetstream\Events\TeamUpdated;
use Laravel\Jetstream\Team as JetstreamTeam;

/**
 * @property-read TeamProfile|null $profile
 */
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

            app(IdentityProtection::class)->evaluate($team, 'team_created');
        });

        // A verified business that renames itself goes back for a reviewer's look (R-29 §13).
        static::updated(function (self $team) {
            if ($team->wasChanged('name')) {
                app(VerificationService::class)->identityChanged($team);
                app(IdentityProtection::class)->evaluate($team, 'team_renamed');
            }
        });
    }

    // ---------------------------------------------------------------- business identity (R-29)

    /**
     * Every verification application this business made, newest first.
     *
     * @return HasMany<BusinessVerification, $this>
     */
    public function verifications(): HasMany
    {
        return $this->hasMany(BusinessVerification::class)->orderByDesc('id');
    }

    /**
     * The business's verification standing: the newest application that was ever approved.
     * A renewal that is still being reviewed does not replace it until it is approved.
     *
     * @return HasOne<BusinessVerification, $this>
     */
    public function standing(): HasOne
    {
        return $this->hasOne(BusinessVerification::class)->ofMany(['id' => 'max'], fn ($q) => $q->whereNotNull('verified_at'));
    }

    /** The application being worked on now, if any. */
    public function openVerification(): ?BusinessVerification
    {
        return $this->verifications()->whereIn('status', BusinessVerification::OPEN)->whereNull('verified_at')->first();
    }

    /** True only while InfoDot's verification is current — the one state that shows the badge. */
    public function isVerified(): bool
    {
        return (bool) $this->standing?->isCurrent();
    }

    public function profile()
    {
        return $this->hasOne(TeamProfile::class);
    }

    /** The profile row, saved on first use, so edit forms always have something to fill. */
    public function profileOrNew(): TeamProfile
    {
        return $this->profile ?? $this->profile()->create([]);
    }

    /** Named for the scoped route binding (`/business/{team}/media/{attachment}`). */
    public function attachments()
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function media()
    {
        return $this->attachments();
    }

    public function currentMedia(string $role)
    {
        return $this->media()->where('role', $role)->whereNull('superseded_at')->latest('id')->first();
    }

    public function gallery()
    {
        return $this->media()->where('role', 'gallery')->whereNull('superseded_at')->orderByDesc('id')->get();
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
