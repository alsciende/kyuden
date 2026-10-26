<?php

declare(strict_types=1);

namespace App\Exception\Api;

use App\Enum\OAuthProvider;

/**
 * The account is already linked to another identity of this provider: it is unlinked first.
 */
class ProviderAlreadyLinkedException extends \RuntimeException
{
    public function __construct(OAuthProvider $provider)
    {
        parent::__construct("The account is already linked to another {$provider->value} account: unlink it first.");
    }
}
