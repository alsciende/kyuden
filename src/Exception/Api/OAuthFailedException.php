<?php

declare(strict_types=1);

namespace App\Exception\Api;

use App\Enum\OAuthProvider;

/**
 * The provider of identities (OAuth2) refused the authorization code (invalid, expired, issued to another client) or
 * answered with no identity.
 */
class OAuthFailedException extends \RuntimeException
{
    public function __construct(OAuthProvider $provider, ?\Throwable $previous = null)
    {
        parent::__construct("The authorization by {$provider->value} failed: start again.", previous: $previous);
    }
}
