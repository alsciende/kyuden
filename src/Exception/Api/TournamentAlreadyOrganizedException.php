<?php

declare(strict_types=1);

namespace App\Exception\Api;

/**
 * The user organizes a tournament still to be played already: one at a time.
 */
class TournamentAlreadyOrganizedException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct('You organize a tournament still to be played already: one at a time.');
    }
}
