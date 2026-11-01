<?php

namespace App\Models;

use App\Services\Identity\IdentityProtection;
use App\Services\Verification\VerificationService;
use App\Support\OwnerCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Collection;
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

    /** @return HasOne<TeamProfile, $this> */
    public function profile(): HasOne
    {
        return $this->hasOne(TeamProfile::class);
    }

    // ---------------------------------------------------------------- directory (R-33)

    /** @return BelongsToMany<BusinessCategory, $this> */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(BusinessCategory::class)->orderBy('sort');
    }

    /** @return HasMany<BusinessService, $this> */
    public function services(): HasMany
    {
        return $this->hasMany(BusinessService::class)->orderBy('sort')->orderBy('id');
    }

    /** @return HasMany<BusinessProject, $this> */
    public function projects(): HasMany
    {
        return $this->hasMany(BusinessProject::class)->orderBy('sort')->orderBy('id');
    }

    /** @return HasMany<BusinessLocation, $this> */
    public function locations(): HasMany
    {
        return $this->hasMany(BusinessLocation::class)->orderBy('sort')->orderBy('id');
    }

    /** @return HasMany<BusinessAnnouncement, $this> */
    public function announcements(): HasMany
    {
        return $this->hasMany(BusinessAnnouncement::class)->latest('published_at')->latest('id');
    }

    /** @return HasMany<Enquiry, $this> */
    public function enquiries(): HasMany
    {
        return $this->hasMany(Enquiry::class);
    }

    /** Whether people may message this business through the InfoDot Inbox (R-33 §5A). */
    public function acceptsEnquiries(): bool
    {
        return ! $this->personal_team && ($this->profile === null || $this->profile->accepts_enquiries !== false);
    }

    /** @return HasMany<BusinessProfileEvent, $this> */
    public function profileEvents(): HasMany
    {
        return $this->hasMany(BusinessProfileEvent::class)->latest('id');
    }

    /**
     * Current pictures or brochures of one role, grouped by the item they belong to (`meta.ref`):
     * service and project pictures, so a page loads them in one query.
     *
     * @return Collection<int, Collection<int, Attachment>>
     */
    public function mediaByRef(string $role): Collection
    {
        return $this->media()->where('role', $role)->whereNull('superseded_at')->orderBy('id')->get()
            ->groupBy(fn (Attachment $a) => (int) ($a->meta['ref'] ?? 0));
    }

    /** The current logo, as one eager-loadable relation for directory listings. */
    public function logo(): MorphOne
    {
        // The constraint goes inside ofMany: filtering outside it would pick the newest file of any kind.
        return $this->morphOne(Attachment::class, 'attachable')->ofMany(['id' => 'max'], fn ($q) => $q->where('role', 'logo')->whereNull('superseded_at'));
    }

    public function visibility(): string
    {
        return $this->profile->visibility ?? TeamProfile::PRIVATE;
    }

    /**
     * Who may open this business's page (R-33 §11): anyone when public, any signed-in person
     * when members-only, and only the business's own people when private. Moderators may always
     * look — reports and identity reviews depend on it. Personal workspaces have no page.
     */
    public function isVisibleTo(?User $user): bool
    {
        if ($this->personal_team) {
            return false;
        }

        return match ($this->visibility()) {
            TeamProfile::PUBLIC => true,
            TeamProfile::MEMBERS => $user !== null,
            default => $user !== null && ($user->belongsToTeam($this) || $user->isModerator()),
        };
    }

    /** Businesses this person may find in the directory: visible to them, and described. @param \Illuminate\Database\Eloquent\Builder<Team> $query */
    public function scopeListedFor(Builder $query, ?User $user): void
    {
        $query->where('personal_team', false)
            ->whereHas('profile', fn ($p) => $p->whereIn('visibility', $user ? [TeamProfile::PUBLIC, TeamProfile::MEMBERS] : [TeamProfile::PUBLIC])
                // A listing with nothing in it helps no one: a business is listed once it says what it does.
                ->whereNotNull('description')->where('description', '!=', ''));
    }

    /** The profile row, saved on first use, so edit forms always have something to fill. */
    public function profileOrNew(): TeamProfile
    {
        // Remember the new row, so a second call on the same model does not try to create another.
        if ($this->profile === null) {
            $this->setRelation('profile', $this->profile()->create(['visibility' => TeamProfile::PRIVATE]));
        }

        return $this->profile;
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
