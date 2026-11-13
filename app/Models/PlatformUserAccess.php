<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A person chosen to use a platform whose entitlement is set to "selected people" (R-50). Owners and administrators
 * never need a row. Written only by App\Services\Platforms\Entitlements.
 */
class PlatformUserAccess extends Model
{
    protected $table = 'platform_user_access';

    protected $guarded = [];
}
