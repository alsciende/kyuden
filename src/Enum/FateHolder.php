<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * What holds fate, besides the general token pool: the fate pool of a player, a card in play, a ring.
 */
enum FateHolder: string
{
    case Player = 'player';
    case Card = 'card';
    case Ring = 'ring';
}
