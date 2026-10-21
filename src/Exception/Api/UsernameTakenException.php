<?php

declare(strict_types=1);

namespace App\Exception\Api;

/**
 * Another account has this username, whatever its case.
 */
class UsernameTakenException extends \RuntimeException
{
    public function __construct(string $username)
    {
        parent::__construct("The username {$username} is taken.");
    }
}
