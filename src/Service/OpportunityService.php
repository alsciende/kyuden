<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Game;
use App\Entity\Player;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\GameState;

/**
 * Opportunity to act in steps resolved in player order: it goes to the next player in player order
 * who has not passed during the current step.
 */
readonly class OpportunityService
{
    public function __construct(
        private GameStateManager $gameStateManager,
        private PlayerStateManager $playerStateManager,
    ) {
    }

    /**
     * Gives the opportunity to act to the next player who has not passed. When every other player has passed,
     * the player keeps it.
     */
    public function passToNextPlayer(Game $game, Player $player): void
    {
        $nextPlayer = $this->getNextPlayerToAct($game, $player);
        if ($nextPlayer instanceof Player) {
            $this->gameStateManager->withState(
                $game,
                fn (GameState $state): GameState => $state->setActivePlayer($nextPlayer),
            );
        }
    }

    /**
     * Players who have passed relinquish their opportunities to act: the next player in player order who has not passed,
     * or null when every other player has passed.
     */
    public function getNextPlayerToAct(Game $game, Player $player): ?Player
    {
        foreach ($this->gameStateManager->getState($game)->getPlayerIdsAfter($player) as $playerId) {
            $next = $game->getPlayer($playerId);
            if (! $this->playerStateManager->getState($next)->isPassed()) {
                return $next;
            }
        }

        return null;
    }
}
