<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Event\AbilityTriggeredEvent;
use App\Exception\Rules\NotControlledCardException;
use App\Service\CardScript\CardScriptRegistry;
use Psr\EventDispatcher\EventDispatcherInterface;
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
        private CardScriptRegistry $cardScriptRegistry,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * The card is controlled by the player and is where its abilities can be used (`trigger_ability` transition of its
     * workflow); it stays where it is. The abilities an attachment grants to the card it is attached to belong to the
     * controller of that card, in whose stack the attachment is. Then the cards reacting to an ability triggered may be
     * triggered (AbilityTriggeredEvent).
     */
    public function trigger(PhysicalCard $card, Player $player): void
    {
        if ($this->getAbilityController($card) !== $player) {
            throw new NotControlledCardException($card, $player);
        }

        // fails unless the card is where its abilities can be used
        $this->workflowRegistry->get($card)->apply($card, 'trigger_ability');
        $this->eventDispatcher->dispatch(new AbilityTriggeredEvent($card->getGame(), $card, $player, $this->getCharacter($card)));
    }

    /**
     * The character triggering the abilities of the card: the card itself in play, or the character an attachment
     * grants them to.
     */
    private function getCharacter(PhysicalCard $card): ?PhysicalCard
    {
        if ($card->isCharacterInPlay()) {
            return $card;
        }

        return $this->cardScriptRegistry->isAbilityGrantedToAttached($card->getLogicalCard()->getId()) ? $card->getAttachedCharacter() : null;
    }

    /**
     * The player could trigger the abilities of the card now (what trigger() accepts), without triggering them.
     */
    public function canTrigger(PhysicalCard $card, Player $player): bool
    {
        return $this->getAbilityController($card) === $player && $this->workflowRegistry->get($card)->can($card, 'trigger_ability');
    }

    private function getAbilityController(PhysicalCard $card): Player
    {
        return $this->cardScriptRegistry->isAbilityGrantedToAttached($card->getLogicalCard()->getId()) ? $card->getStack()->getPlayer() : $this->controlService->getController($card);
    }
}
