<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    /** @return HasOne<PlatformBranding, $this> */
    public function branding(): HasOne
    {
        return $this->hasOne(PlatformBranding::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public static function findByClientId(?string $clientId): ?self
    {
        return $clientId ? static::where('oauth_client_id', $clientId)->first() : null;
    }

    /**
     * Exact-match check against the registered post-logout redirect URIs (no wildcards, R-20 §7E).
     * The one tolerance: a bare origin and the same origin with a lone trailing slash are the same
     * address (https://x and https://x/ both mean the origin root), so a client that sends either
     * form for a registered origin root still lands back on its platform. Paths, queries and hosts
     * are still compared exactly.
     */
    public function allowsPostLogoutRedirect(string $uri): bool
    {
        $wanted = self::canonicalOriginRoot($uri);

        foreach ($this->post_logout_redirect_uris ?? [] as $registered) {
            if (is_string($registered) && self::canonicalOriginRoot($registered) === $wanted) {
                return true;
            }
        }

        return false;
    }

    private static function canonicalOriginRoot(string $uri): string
    {
        return preg_match('#^(https?://[^/?\#]+)/?$#i', $uri, $m) === 1 ? $m[1].'/' : $uri;
    }
}
