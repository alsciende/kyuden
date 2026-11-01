<?php

declare(strict_types=1);

namespace App\Workflow;

use App\Entity\PhysicalCard;
use App\Entity\Player;
use App\Enum\StackType;
use App\Factory\StackFactory;
use App\State\Manager\CardStateManager;
use App\State\Manager\GameStateManager;
use App\State\Model\CardState;
use App\State\Model\GameState;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;

/**
 * Consequences of the `take_control` transition of a card in play, what its metadata cannot express ("Control and
 * Ownership" in the glossary), for the player given in the `controller` key of the context:
 * - the card records its controller (CardState::controllerId, null when it is its owner);
 * - a character moves to a new stack of its controller, its attachments with it, each keeping its orientation (the
 *   attachments do not change control);
 * - a participating character participates on the side of its controller.
 */
#[AsTransitionListener(workflow: 'conflict_card', transition: 'take_control')]
#[AsTransitionListener(workflow: 'dynasty_card', transition: 'take_control')]
readonly class TakeControlListener
{
    public function __construct(
        private CardStateManager $cardStateManager,
        private GameStateManager $gameStateManager,
        private StackFactory $stackFactory,
    ) {
    }

    /**
     * @param TransitionEvent<PhysicalCard> $event
     */
    public function __invoke(TransitionEvent $event): void
    {
        $card = $event->getSubject();
        $controller = $event->getContext()['controller'] ?? null;
        if (! $controller instanceof Player) {
            throw new \LogicException("The 'controller' key of the transition context must be a Player.");
        }

        $this->cardStateManager->withState(
            $card,
            fn (CardState $state): CardState => $state->setControllerId($controller === $card->getPlayer() ? null : $controller->getId()),
        );

        if (! $card->entersPlayInNewStack()) {
            return;
        }

        $stack = $this->stackFactory->createStack($controller, StackType::InPlay);
        foreach ($card->getStack()->getOrderedCards() as $stacked) {
            $stack->addCard($stacked);
        }

        $this->gameStateManager->withState($card->getGame(), function (GameState $state) use ($card, $controller): GameState {
            $conflict = $state->conflict;
            if ($conflict === null || ! in_array($card->getId(), [...$conflict->attackerIds, ...$conflict->defenderIds], true)) {
                return $state;
            }

            $conflict->attackerIds = array_values(array_diff($conflict->attackerIds, [$card->getId()]));
            $conflict->defenderIds = array_values(array_diff($conflict->defenderIds, [$card->getId()]));
            match ($controller->getId()) {
                $conflict->attackingPlayerId => $conflict->attackerIds[] = $card->getId(),
                $conflict->defendingPlayerId => $conflict->defenderIds[] = $card->getId(),
                default => null,
            };

            return $state;
        });
    }
}
