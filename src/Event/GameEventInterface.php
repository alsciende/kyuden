<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;

/**
 * A domain event of a game.
 */
interface GameEventInterface
{
    public function getGame(): Game;
}
