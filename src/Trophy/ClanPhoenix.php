<?php

declare(strict_types=1);

namespace App\Trophy;

use App\Enum\Clan;

readonly class ClanPhoenix extends AbstractClanTrophy
{
    #[\Override]
    public static function getClan(): Clan
    {
        return Clan::Phoenix;
    }
}
