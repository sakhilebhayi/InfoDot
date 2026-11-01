<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One change to a business's public presence (R-33 §11 audit logging): who did what, and when.
 * Append-only; shown to the business's owner and administrators and to moderators.
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $detail
 * @property Carbon $created_at
 */
class BusinessProfileEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    /** How each action reads in the log. */
    public const ACTIONS = [
        'profile_saved' => 'Saved the profile details',
        'listing_changed' => 'Changed who can find the business',
        'contact_changed' => 'Changed which contact details are public',
        'media_added' => 'Added a picture',
        'media_removed' => 'Took a picture down',
        'service_added' => 'Added an offering',
        'service_changed' => 'Changed an offering',
        'service_removed' => 'Removed an offering',
        'project_added' => 'Added a project',
        'project_changed' => 'Changed a project',
        'project_removed' => 'Removed a project',
        'location_added' => 'Added a location',
        'location_changed' => 'Changed a location',
        'location_removed' => 'Removed a location',
        'announcement_published' => 'Published an announcement',
        'announcement_withdrawn' => 'Withdrew an announcement',
        'brochure_added' => 'Added a brochure',
        'brochure_removed' => 'Took a brochure down',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return self::ACTIONS[$this->action] ?? str_replace('_', ' ', $this->action);
    }

    public static function record(Team $team, ?User $user, string $action, ?string $detail = null): self
    {
        return self::create([
            'team_id' => $team->id,
            'user_id' => $user?->id,
            'action' => $action,
            'detail' => $detail !== null ? mb_substr($detail, 0, 255) : null,
        ]);
    }
}
