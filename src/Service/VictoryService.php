<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CardTypes\ProvinceCard;
use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\VictoryCondition;
use App\Model\Victory;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\PlayerState;

/**
 * Stronghold format victory conditions: a player with 25 or more honor wins; a player who has conceded, has no honor or
 * whose stronghold province is broken is eliminated, and the last player not eliminated wins. A player who has
 * conceded cannot win. When several players meet a
 * condition at once, the first player wins if they meet one, otherwise the next one in player order.
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
        $eliminations = [];
        foreach ($players as $player) {
            $condition = $this->findElimination($player);
            if ($condition instanceof VictoryCondition) {
                $eliminations[$player->getId()] = $condition;
            }
        }

        foreach ($players as $player) {
            $honor = $this->playerStateManager->getState($player)->getHonor();
            $conceded = ($eliminations[$player->getId()] ?? null) === VictoryCondition::Concession;
            if ($honor !== null && $honor >= PlayerState::MAXIMUM_HONOR && ! $conceded) {
                return new Victory($player, VictoryCondition::HonorWin);
            }

            if (count($eliminations) === count($players) - 1 && ! isset($eliminations[$player->getId()])) {
                // the condition that eliminated the first eliminated opponent in player order
                return new Victory($player, array_first($eliminations) ?? throw new \LogicException('Expected an eliminated player.'));
            }
        }

        return null;
    }

    /**
     * The victory condition met by the opponents of an eliminated player, or null if the player is not eliminated.
     */
    private function findElimination(Player $player): ?VictoryCondition
    {
        $state = $this->playerStateManager->getState($player);
        if ($state->isConceded()) {
            return VictoryCondition::Concession;
        }

        $honor = $state->getHonor();
        if ($honor !== null && $honor <= PlayerState::MINIMUM_HONOR) {
            return VictoryCondition::HonorLoss;
        }

        $strongholdProvince = $player->getPhysicalCards()->findFirst(
            fn (int $key, PhysicalCard $card): bool => $card->getId() === $state->getStrongholdProvinceId(),
        );
        if ($strongholdProvince instanceof ProvinceCard && $strongholdProvince->getCurrentPlace() === ProvinceCard::STATE_BROKEN) {
            return VictoryCondition::StrongholdProvinceBroken;
        }

        return null;
    }
}
