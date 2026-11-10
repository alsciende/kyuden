<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Game;
use App\Entity\Player;
use App\GameLog\GameLog;

/**
 * The next action opportunities given in an action window go to a player (GiveActionOpportunity): those given now, not
 * the total when they add up.
 */
#[GameLog('{player} takes the next action opportunities: {actions}')]
final readonly class ActionOpportunitiesGivenEvent implements GameEventInterface
{
    public function __construct(
        public Game $game,
        public Player $player,
        public int $actions,
    ) {
    }

    #[\Override]
    public function getGame(): Game
    {
        return $this->game;
    }
}
