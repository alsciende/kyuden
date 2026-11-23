<?php

declare(strict_types=1);

namespace App\Service\Interrupt;

use App\Entity\Game;
use App\Exception\Data\DataException;
use App\Message\ActionInterface;
use App\Message\LoseHonor;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\Trigger\HonorLosingTrigger;
use App\Workflow\Game\UnopposedAppliedTransitionListener;

/**
 * A player would lose honor (HonorLosingTrigger): by an effect (LoseHonor), or the defending player for an unopposed
 * conflict (`unopposed_applied`). Not when they have none left to lose.
 */
final readonly class HonorLossTriggerResolver implements ImminentTriggerResolverInterface
{
    public function __construct(
        private GameStateManager $gameStateManager,
        private PlayerStateManager $playerStateManager,
    ) {
    }

    #[\Override]
    public function resolveMessage(ActionInterface $message, Game $game): array
    {
        if (! $message instanceof LoseHonor || $message->getHonorLoss() <= 0) {
            return [];
        }

        try {
            $game->getPlayer($message->getPlayerId());
            $player = $game->getPlayer($message->getTargetPlayerId());
        } catch (DataException) {
            // refused by its handler: nothing would occur
            return [];
        }

        return $this->playerStateManager->getState($player)->getHonor() > 0
            ? [new HonorLosingTrigger($player, $message->getHonorLoss())]
            : [];
    }

    #[\Override]
    public function resolveTransition(string $transition, Game $game): array
    {
        if ($transition !== 'unopposed_applied') {
            return [];
        }

        $conflict = $this->gameStateManager->getState($game)->currentConflict();
        if (! $conflict->attackerWon() || $conflict->defenderIds !== []) {
            return [];
        }

        $defender = $game->getPlayer($conflict->defendingPlayerId);

        return $this->playerStateManager->getState($defender)->getHonor() > 0
            ? [new HonorLosingTrigger($defender, UnopposedAppliedTransitionListener::UNOPPOSED_HONOR_LOSS, unopposed: true)]
            : [];
    }
}
