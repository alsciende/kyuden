<?php

declare(strict_types=1);

namespace App\State\Manager;

use App\Entity\Player;
use App\State\Model\PlayerState;

/**
 * @extends AbstractStateManager<Player, PlayerState>
 */
readonly class PlayerStateManager extends AbstractStateManager
{
    protected function getStateClass(): string
    {
        return PlayerState::class;
    }
}
