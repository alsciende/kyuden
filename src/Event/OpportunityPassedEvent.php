<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\GameLog\GameLog;

/**
 * A player has passed their opportunity to act (PassOpportunity), before the opportunity goes to the next player.
 * Listeners check the game place to apply the rules of the step.
 */
#[GameLog('{player} passes')]
final readonly class OpportunityPassedEvent implements GameEventInterface
{
    public function __construct(
        private Game $game,
        private Player $player,
        private bool $firstToPass,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }

    public function getPlayer(): Player
    {
        return $this->player;
    }

    /**
     * No other player had passed during the step.
     */
    public function isFirstToPass(): bool
    {
        return $this->firstToPass;
    }
}
