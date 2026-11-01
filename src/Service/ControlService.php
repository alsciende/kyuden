<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\State\Manager\CardStateManager;

/**
 * The controller of a card ("Control and Ownership" in the glossary): its owner, unless another player has taken
 * control of it in play (TakeControl), which lasts until it leaves play (its CardState is reset). A player controls the
 * cards out of play they own. A character in play is in a stack of its controller.
 */
final readonly class ControlService
{
    public function __construct(
        private CardStateManager $cardStateManager,
    ) {
    }

    public function getController(PhysicalCard $card): Player
    {
        $controllerId = $this->cardStateManager->getState($card)->getControllerId();

        return $controllerId === null ? $card->getPlayer() : $card->getGame()->getPlayer($controllerId);
    }
}
