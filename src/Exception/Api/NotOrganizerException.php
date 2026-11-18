<?php

declare(strict_types=1);

namespace App\Exception\Api;

/**
 * Only the organizer of a tournament cancels it.
 */
class NotOrganizerException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('Only the organizer of the tournament cancels it.');
    }
}
