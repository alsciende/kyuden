<?php

declare(strict_types=1);

namespace App\Exception\Api;

/**
 * The state sent back from a provider of identities (OAuth2) is unknown, has expired, was used already, or was issued
 * for another provider, purpose or user: the human starts again.
 */
class InvalidOAuthStateException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The authorization is unknown, has expired or does not match this request: start again.');
    }
}
