<?php

declare(strict_types=1);

namespace App\Exception\Api;

/**
 * The user is already in the matchmaking queue: a user waits in it once.
 */
class AlreadyQueuedException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The user is already in the queue.');
    }
}
