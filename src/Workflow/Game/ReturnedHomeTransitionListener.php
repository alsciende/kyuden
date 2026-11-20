<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\Enum\Orientation;
use App\Event\CardBowedEvent;
use App\Event\ConflictResolvedEvent;
use App\Repository\CardRepository;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Workflow\Registry;

/**
 * 3.2.8: each ready participating character bows, then every participating character returns home; the resolution of
 * the conflict is over (ConflictResolvedEvent).
 */
#[AsTransitionListener(workflow: 'game', transition: 'returned_home')]
readonly class ReturnedHomeTransitionListener
{
    public function __construct(
        private GameStateManager $stateManager,
        private CardRepository $cardRepository,
        private Registry $workflowRegistry,
        private EventDispatcherInterface $eventDispatcher,
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
                $this->eventDispatcher->dispatch(new CardBowedEvent($game, $card, false));
            }
        }

        // read before the participants return home
        $resolved = new ConflictResolvedEvent($game, $game->getPlayer($conflict->attackingPlayerId), $conflict->attackerIds, $conflict->brokenProvinceIds);
        $this->stateManager->withState($game, function (GameState $state): GameState {
            $state->currentConflict()->attackerIds = [];
            $state->currentConflict()->defenderIds = [];

            return $state;
        });
        $this->eventDispatcher->dispatch($resolved);
    }
}
