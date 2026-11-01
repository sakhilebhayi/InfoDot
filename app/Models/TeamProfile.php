<?php

namespace App\Models;

use App\Services\Identity\IdentityProtection;
use App\Services\Verification\VerificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The public business profile of a company (R-29 Part A). Who may see it is the business's
 * choice (`visibility`, R-33); which contact details show is too (`public_contact`). Sensitive
 * verification material never enters this table.
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
 * @property string $visibility
 * @property string|null $business_type
 * @property array<string, bool>|null $public_contact
 * @property string|null $industries_served
 * @property bool $accepts_enquiries
 * @property Carbon|null $restricted_at
 */
class TeamProfile extends Model
{
    /** The social/professional link slots the profile form offers. */
    public const LINK_KEYS = ['linkedin', 'facebook', 'instagram', 'x', 'youtube'];

    /** The contact details a business may show or keep off its page. */
    public const CONTACT_FIELDS = ['phone' => 'Business phone', 'email' => 'Business email', 'website' => 'Website', 'links' => 'Social and professional links'];

    /** Who can find and open the business (R-33 §11). */
    public const PUBLIC = 'public';

    public const MEMBERS = 'members';

    public const PRIVATE = 'private';

    public const VISIBILITY = [
        self::PUBLIC => 'Anyone — listed in the directory, and the page opens without signing in',
        self::MEMBERS => 'InfoDot users — listed for people signed in to InfoDot',
        self::PRIVATE => 'Only this business — not listed; the page is for your own team',
    ];

    public const BUSINESS_TYPES = [
        'company' => 'Registered company',
        'sole_proprietor' => 'Sole proprietor',
        'partnership' => 'Partnership',
        'cooperative' => 'Cooperative',
        'non_profit' => 'Non-profit organisation',
        'public_entity' => 'Public entity',
        'informal' => 'Informal business',
    ];

    protected $fillable = [
        'trading_name', 'description', 'industry', 'category', 'business_type', 'visibility', 'products_services', 'industries_served',
        'location', 'regions', 'website', 'contact_email', 'contact_phone',
        'registration_number', 'registration_country', 'established_on', 'size', 'links',
    ];

    /** Fields a verification vouched for: changing one sends a verified business back for review (R-29 §13). */
    public const IDENTITY_FIELDS = ['trading_name', 'registration_number', 'registration_country', 'website'];

    protected static function booted(): void
    {
        static::saved(function (self $profile) {
            if ($profile->wasChanged(self::IDENTITY_FIELDS) && $profile->team) {
                app(VerificationService::class)->identityChanged($profile->team);
            }

            // Compared with protected identities whenever identity details are written (R-29 §15–16).
            if (($profile->wasRecentlyCreated || $profile->wasChanged([...self::IDENTITY_FIELDS, 'contact_email'])) && $profile->team) {
                app(IdentityProtection::class)->evaluate($profile->team, 'profile_updated');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'established_on' => 'date',
            'links' => 'array',
            'public_contact' => 'array',
            'accepts_enquiries' => 'boolean',
            'restricted_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Whether a contact detail shows on the business page (R-33 §5B). The business decides; a
     * profile that never chose shows everything it filled in, as it did before the choice existed.
     */
    public function shows(string $field): bool
    {
        return (bool) ($this->public_contact[$field] ?? true);
    }

    /** True once the profile says anything at all — the public page hides empty sections. */
    public function hasContent(): bool
    {
        return collect($this->only(array_diff($this->fillable, ['links', 'visibility'])))->filter()->isNotEmpty()
            || ! empty(array_filter($this->links ?? []));
    }
}
