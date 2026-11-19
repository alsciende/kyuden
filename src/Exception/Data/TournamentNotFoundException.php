<?php

declare(strict_types=1);

namespace App\Exception\Data;

class TournamentNotFoundException extends NotFoundException
{
    public function __construct(string $tournamentId)
    {
        parent::__construct("Tournament not found: [{$tournamentId}]");
    }
}
