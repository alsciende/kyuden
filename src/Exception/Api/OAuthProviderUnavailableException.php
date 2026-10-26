<?php

declare(strict_types=1);

namespace App\Exception\Api;

use App\Enum\OAuthProvider;

/**
 * The provider of identities (OAuth2) could not be reached.
 */
class OAuthProviderUnavailableException extends \RuntimeException
{
    public function __construct(OAuthProvider $provider, ?\Throwable $previous = null)
    {
        parent::__construct("The provider {$provider->value} cannot be reached: try again later.", previous: $previous);
    }
}
