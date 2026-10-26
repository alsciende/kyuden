<?php

declare(strict_types=1);

namespace App\Security\OAuth\Provider;

use Wohali\OAuth2\Client\Provider\Discord as WohaliDiscord;

/**
 * Discord, asking for the user only (`identify`: no email, guilds nor connections), with PKCE.
 */
class Discord extends WohaliDiscord
{
    /**
     * @return list<string>
     */
    #[\Override]
    protected function getDefaultScopes(): array
    {
        return ['identify'];
    }

    #[\Override]
    protected function getPkceMethod(): string
    {
        return self::PKCE_METHOD_S256;
    }
}
