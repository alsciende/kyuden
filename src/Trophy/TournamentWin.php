<?php

declare(strict_types=1);

namespace App\Trophy;

use App\Entity\Tournament;
use App\Entity\TournamentEntry;
use App\Enum\Trophy;

/**
 * Finish first in a tournament: its winner, whatever the size of its bracket, the final won by forfeit or by a bye
 * included.
 */
readonly class TournamentWin implements TournamentTrophyInterface
{
    #[\Override]
    public static function getTrophy(): Trophy
    {
        return Trophy::TournamentWin;
    }

    #[\Override]
    public function isEarnedBy(TournamentEntry $entry, Tournament $tournament): bool
    {
        return $tournament->getWinner() === $entry;
    }
}
