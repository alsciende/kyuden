<?php

declare(strict_types=1);

namespace App\Trophy;

readonly class TournamentWin16 extends AbstractTournamentWinTrophy
{
    #[\Override]
    public static function getSize(): int
    {
        return 16;
    }
}
