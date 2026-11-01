<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Exception\Rules\NotControlledCardException;
use Symfony\Component\Workflow\Registry;

/**
 * Rules about triggering the abilities of a card. Neither the presence of an ability of that timing on the card, its
 * triggering condition nor its limit per round is checked, since card effects can change them.
 */
readonly class AbilityService
{
    public function __construct(
        private Registry $workflowRegistry,
        private ControlService $controlService,
    ) {
    }

    /**
     * The card is controlled by the player and is where its abilities can be used (`trigger_ability` transition of its
     * workflow); it stays where it is. The abilities an attachment grants to the card it is attached to belong to the
     * controller of that card, in whose stack the attachment is.
     */
    public function trigger(PhysicalCard $card, Player $player): void
    {
        $abilityController = $card->getLogicalCard()->isAbilityGrantedToAttached() ? $card->getStack()->getPlayer() : $this->controlService->getController($card);
        if ($abilityController !== $player) {
            throw new NotControlledCardException($card, $player);
        }

        // fails unless the card is where its abilities can be used
        $this->workflowRegistry->get($card)->apply($card, 'trigger_ability');
    }
}
