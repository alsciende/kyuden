<?php

declare(strict_types=1);

namespace App\Exception\Api;

/**
 * The user is not registered in the tournament.
 */
class NotRegisteredException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('You are not registered in this tournament.');
    }
}
