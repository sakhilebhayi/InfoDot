<?php

namespace App\Support;

use App\Models\User;
use Laravel\Passport\Bridge\AccessTokenRepository;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;

/**
 * The one place every grant passes through before a token exists (code exchange and refresh alike). A suspended
 * account is refused here as an invalid grant, so the token endpoint cannot hand a platform new tokens for it
 * whatever the platform still holds.
 */
class SuspensionAwareAccessTokenRepository extends AccessTokenRepository
{
    public function getNewToken(
        ClientEntityInterface $clientEntity,
        array $scopes,
        ?string $userIdentifier = null
    ): AccessTokenEntityInterface {
        if ($userIdentifier !== null && User::whereKey($userIdentifier)->whereNotNull('suspended_at')->exists()) {
            throw OAuthServerException::invalidGrant();
        }

        return parent::getNewToken($clientEntity, $scopes, $userIdentifier);
    }
}
