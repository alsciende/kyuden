<?php

declare(strict_types=1);

namespace App\Trophy;

use App\Entity\Tournament;
use App\Entity\TournamentEntry;
use App\Enum\Trophy;

/**
 * Win a tournament whose bracket has the size (not the number of its players), the final won by forfeit or by a bye
 * included.
 */
abstract readonly class AbstractTournamentWinTrophy implements TournamentTrophyInterface
{
    /**
     * The size of the bracket: 2, 4, 8, 16, 32 or 64.
     */
    abstract public static function getSize(): int;

    #[\Override]
    public static function getTrophy(): Trophy
    {
        return Trophy::ofTournamentWin(static::getSize());
    }

    #[\Override]
    public function isEarnedBy(TournamentEntry $entry, Tournament $tournament): bool
    {
        return $tournament->getWinner() === $entry && $tournament->getSize() === static::getSize();
    }
}
