<?php

declare(strict_types=1);

namespace App\Trophy;

use App\Enum\Clan;

readonly class ClanScorpion extends AbstractClanTrophy
{
    #[\Override]
    public static function getClan(): Clan
    {
        return Clan::Scorpion;
    }
}
