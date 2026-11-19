<?php

declare(strict_types=1);

namespace App\Service\CardScript;

use App\Entity\Game;
use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\Element;
use App\Service\ControlService;
use App\State\Manager\GameStateManager;
use App\State\Model\ConflictState;

/**
 * The conditions the triggered abilities of the cards often share, for their scripts (AbstractCardScript), which read
 * the state as it is when called. A condition no helper covers reads the state directly.
 */
final readonly class TriggerConditions
{
    public function __construct(
        private ControlService $controlService,
        private GameStateManager $gameStateManager,
    ) {
    }

    /**
     * The player controls the card ("a province you control").
     */
    public function isControlledBy(PhysicalCard $card, Player $player): bool
    {
        return $this->controlService->getController($card) === $player;
    }

    /**
     * The card has the element ("an [element-air] province").
     */
    public function hasElement(PhysicalCard $card, Element $element): bool
    {
        return in_array($element, $card->getLogicalCard()->getElements() ?? [], true);
    }

    /**
     * A conflict is being resolved, from its declaration until its participants return home.
     */
    public function isConflictInProgress(Game $game): bool
    {
        return $this->gameStateManager->getState($game)->conflict instanceof ConflictState;
    }

    /**
     * The character participates in the conflict being resolved, on either side.
     */
    public function isParticipating(PhysicalCard $character): bool
    {
        $conflict = $this->gameStateManager->getState($character->getGame())->conflict;

        return $conflict instanceof ConflictState
            && in_array($character->getId(), [...$conflict->attackerIds, ...$conflict->defenderIds], true);
    }
}
