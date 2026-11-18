<?php

declare(strict_types=1);

namespace App\Exception\Api;

use App\Entity\Tournament;

/**
 * The tournament has as many players as it takes (Tournament::MAX_ENTRANTS).
 */
class TournamentFullException extends \RuntimeException
{
    public function __construct()
    {
        parent::__construct(sprintf('The tournament is full: %d players at most.', Tournament::MAX_ENTRANTS));
    }
}
