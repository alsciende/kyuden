<?php

declare(strict_types=1);

namespace App\State\Manager;

use App\Entity\Game;
use App\State\Model\GameState;

/**
 * @extends AbstractStateManager<Game, GameState>
 */
readonly class GameStateManager extends AbstractStateManager
{
    protected function getStateClass(): string
    {
        return GameState::class;
    }
}
