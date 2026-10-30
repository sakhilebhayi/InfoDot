<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A registered relying party in the Dot ecosystem (R-20 §2). The identity provider only answers
 * for platforms that exist here with status `active`; `oauth_client_id` points at the Passport
 * client that holds the registered redirect URIs and the (hashed-at-rest-by-env) secret.
 */
class DotPlatform extends Model
{
    protected $guarded = [];

    protected $casts = [
        'redirect_uris' => 'array',
        'post_logout_redirect_uris' => 'array',
        'scopes' => 'array',
        'last_authenticated_at' => 'datetime',
    ];

    /** @return HasMany<SsoPlatformSession, $this> */
    public function sessions(): HasMany
    {
        return $this->hasMany(SsoPlatformSession::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public static function findByClientId(?string $clientId): ?self
    {
        return $clientId ? static::where('oauth_client_id', $clientId)->first() : null;
    }

    /** Exact-match check against the registered post-logout redirect URIs (no wildcards, R-20 §7E). */
    public function allowsPostLogoutRedirect(string $uri): bool
    {
        return in_array($uri, $this->post_logout_redirect_uris ?? [], true);
    }
}
