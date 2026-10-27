<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\AccessToken;
use Symfony\Contracts\Service\ResetInterface;

/**
 * The access token the request was authenticated with (AccessTokenHandler), so that what the token is limited to (the
 * invitation of a bot) is known beyond its user.
 */
final class CurrentAccessToken implements ResetInterface
{
    private ?AccessToken $accessToken = null;

    public function get(): ?AccessToken
    {
        return $this->accessToken;
    }

    public function set(AccessToken $accessToken): void
    {
        $this->accessToken = $accessToken;
    }

    #[\Override]
    public function reset(): void
    {
        $this->accessToken = null;
    }
}
