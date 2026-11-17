<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * R-54: the presentation record of one registered platform (1:1 on dot_platforms). It holds words, colours and the
 * paths of two uploaded images, and nothing secret. Views never read this model: they read the allowlisted
 * App\Support\Branding\PlatformBrandingView built from it.
 *
 * @property int $dot_platform_id
 * @property int $version
 * @property bool $active
 */
class PlatformBranding extends Model
{
    public const MODES = ['light', 'dark'];

    /** A colour is `#rrggbb`, nothing else: it is printed into a style rule, so no other form is ever accepted. */
    public const HEX = '/\A#[0-9a-fA-F]{6}\z/';

    protected $table = 'platform_branding';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['approved_redirect_domains' => 'array', 'active' => 'boolean', 'version' => 'integer'];
    }

    /** @return BelongsTo<DotPlatform, $this> */
    public function platform(): BelongsTo
    {
        return $this->belongsTo(DotPlatform::class, 'dot_platform_id');
    }

    /** @return BelongsTo<User, $this> */
    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
