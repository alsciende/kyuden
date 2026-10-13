<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Game;
use App\Entity\Player;
use App\Enum\VictoryCondition;
use App\Model\Victory;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\PlayerState;

/**
 * Stronghold format victory conditions: a player with 25 or more honor wins, a player with no honor is eliminated and
 * the last player not eliminated wins. When several players meet a condition at once, the first player wins if they
 * meet one, otherwise the next one in player order.
 */
readonly class VictoryService
{
    public function __construct(
        private GameStateManager $gameStateManager,
        private PlayerStateManager $playerStateManager,
    ) {
    }

    public function findVictory(Game $game): ?Victory
    {
        $gameState = $this->gameStateManager->getState($game);
        if ($gameState->firstPlayerId === null) {
            return null;
        }

        $firstPlayer = $game->getPlayer($gameState->firstPlayerId);
        $players = [$firstPlayer, ...array_map($game->getPlayer(...), $gameState->getPlayerIdsAfter($firstPlayer))];
        $eliminated = array_filter($players, $this->isEliminated(...));

        foreach ($players as $player) {
            $honor = $this->playerStateManager->getState($player)->getHonor();
            if ($honor !== null && $honor >= PlayerState::MAXIMUM_HONOR) {
                return new Victory($player, VictoryCondition::HonorWin);
            }

            if (count($eliminated) === count($players) - 1 && ! in_array($player, $eliminated, true)) {
                return new Victory($player, VictoryCondition::HonorLoss);
            }
        }

        return null;
    }

    private function isEliminated(Player $player): bool
    {
        $honor = $this->playerStateManager->getState($player)->getHonor();

        return $honor !== null && $honor <= PlayerState::MINIMUM_HONOR;
    }
}
