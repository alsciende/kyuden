<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\Enum\Orientation;
use App\Repository\CardRepository;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Workflow\Registry;

/**
 * 3.2.8: each ready participating character bows, then every participating character returns home.
 */
#[AsTransitionListener(workflow: 'game', transition: 'returned_home')]
readonly class ReturnedHomeTransitionListener
{
    public function __construct(
        private GameStateManager $stateManager,
        private CardRepository $cardRepository,
        private Registry $workflowRegistry,
    ) {
    }

    /**
     * @param TransitionEvent<Game> $event
     */
    public function __invoke(TransitionEvent $event): void
    {
        $game = $event->getSubject();
        $conflict = $this->stateManager->getState($game)->currentConflict();

        foreach ([...$conflict->attackerIds, ...$conflict->defenderIds] as $cardId) {
            $card = $this->cardRepository->get($cardId, $game);
            if ($card->getOrientation() === Orientation::FaceUp) {
                $this->workflowRegistry->get($card)->apply($card, 'bow');
            }
        }

        $this->stateManager->withState($game, function (GameState $state): GameState {
            $state->currentConflict()->attackerIds = [];
            $state->currentConflict()->defenderIds = [];

            return $state;
        });
    }
}
