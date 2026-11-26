<?php

namespace App\Entities;

use App\Models\User;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use OpenIDConnect\Claims\Traits\WithClaims;
use OpenIDConnect\Interfaces\IdentityEntityInterface;

/**
 * Maps the authenticated user onto the id_token. The subject (`sub`) is users.uuid — the stable,
 * immutable ecosystem identity (R-20 §11: never the email, and not the numeric id, which is
 * internal to this database). The claims below are filtered by the granted scopes (profile, email)
 * before they reach the token.
 *
 * Nothing in app/ names this class: the OIDC package's IdentityRepository loads App\Entities\IdentityEntity by its class name
 * when it exists (vendor/jeremy379/laravel-openid-connect). Renaming or moving it silently puts the numeric id back in `sub`.
 */
class IdentityEntity implements IdentityEntityInterface
{
    use EntityTrait;
    use WithClaims;

    protected User $user;

    /**
     * Passport hands over its internal user identifier (users.id); the entity re-keys itself to
     * the uuid so every token this provider signs speaks the ecosystem identity.
     */
    public function setIdentifier($identifier): void
    {
        $this->user = User::findOrFail($identifier);
        $this->identifier = (string) $this->user->uuid;
    }

    /** @return array<string, bool|string> */
    public function getClaims(array $scopes = []): array
    {
        return [
            'name' => $this->user->name,
            'email' => $this->user->email,
            'email_verified' => $this->user->email_verified_at !== null,
        ];
    }
}
