<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Game;
use App\Entity\Player;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\GameState;

/**
 * Opportunity to act in steps with opportunities. In steps resolved in player order, a single player is active and
 * the opportunity goes to the next player in player order who has not passed during the current step. In steps where
 * players act simultaneously, every player is active until they pass.
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
     * the player keeps it. Players acting simultaneously keep their opportunity.
     */
    public function passToNextPlayer(Game $game, Player $player): void
    {
        if ($this->gameStateManager->getState($game)->countActivePlayers() > 1) {
            return;
        }

        $this->activateNextPlayer($game, $player);
    }

    /**
     * The player who has passed is no longer active. When no other player is active, the opportunity goes to the next
     * player who has not passed.
     */
    public function pass(Game $game, Player $player): void
    {
        $this->gameStateManager->withState(
            $game,
            fn (GameState $state): GameState => $state->deactivatePlayer($player),
        );
        if ($this->gameStateManager->getState($game)->countActivePlayers() === 0) {
            $this->activateNextPlayer($game, $player);
        }
    }

    private function activateNextPlayer(Game $game, Player $player): void
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
