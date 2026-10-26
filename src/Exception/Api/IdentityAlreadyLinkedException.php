<?php

declare(strict_types=1);

namespace App\Exception\Api;

use App\Enum\OAuthProvider;

/**
 * The identity at the provider is already linked to another account.
 */
class IdentityAlreadyLinkedException extends \RuntimeException
{
    public function __construct(OAuthProvider $provider)
    {
        parent::__construct("This {$provider->value} account is already linked to another account.");
    }
}
