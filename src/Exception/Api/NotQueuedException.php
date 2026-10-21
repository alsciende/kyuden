<?php

declare(strict_types=1);

namespace App\Exception\Api;

/**
 * The user is not in the matchmaking queue: they never joined it, left it or have been matched.
 */
class NotQueuedException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('The user is not in the queue.');
    }
}
