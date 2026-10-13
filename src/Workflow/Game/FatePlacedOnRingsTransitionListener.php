<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\Enum\Element;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;

/**
 * 4.4: 1 fate from the general token pool is placed on each unclaimed ring.
 */
#[AsTransitionListener(workflow: 'game', transition: 'fate_placed_on_rings')]
readonly class FatePlacedOnRingsTransitionListener
{
    public const int FATE_PLACED = 1;

    public function __construct(
        private GameStateManager $stateManager,
    ) {
    }

    public function __invoke(TransitionEvent $event): void
    {
        /** @var Game $game */
        $game = $event->getSubject();

        $this->stateManager->withState($game, function (GameState $state): GameState {
            foreach (Element::cases() as $element) {
                if ($state->isRingUnclaimed($element)) {
                    $state->getRing($element)->fate += self::FATE_PLACED;
                }
            }

            return $state;
        });
    }
}
