<?php

namespace App\Models\Passport;

use App\Models\DotPlatform;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Passport\Client as BaseClient;

/**
 * Every client of this identity provider is a first-party Dot platform, registered and active in
 * the dot_platforms registry — so the consent screen is skipped (R-20 §9: SSO without re-prompting).
 * A client without an active registry row never reaches this check: the authorize endpoint rejects
 * it first (EnforceOidcRules).
 */
class Client extends BaseClient
{
    public function skipsAuthorization(Authenticatable $user, array $scopes): bool
    {
        return DotPlatform::findByClientId($this->getKey())?->isActive() ?? false;
    }
}
