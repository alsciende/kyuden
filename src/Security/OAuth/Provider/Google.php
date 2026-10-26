<?php

declare(strict_types=1);

namespace App\Security\OAuth\Provider;

use League\OAuth2\Client\Provider\Google as LeagueGoogle;

/**
 * Google (OpenID Connect), asking for the identifier of the identity only (`openid`: no email nor profile), with PKCE.
 */
class Google extends LeagueGoogle
{
    /**
     * @return list<string>
     */
    #[\Override]
    protected function getDefaultScopes(): array
    {
        return ['openid'];
    }

    #[\Override]
    protected function getPkceMethod(): string
    {
        return self::PKCE_METHOD_S256;
    }
}
