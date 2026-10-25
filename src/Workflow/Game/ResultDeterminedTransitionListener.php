<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\Event\ConflictUnwonEvent;
use App\Event\ConflictWonEvent;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;

/**
 * 3.2.3: the winner of the conflict, if any, from the declared skill totals.
 */
#[AsTransitionListener(workflow: 'game', transition: 'result_determined')]
readonly class ResultDeterminedTransitionListener
{
    public function __construct(
        private GameStateManager $stateManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param TransitionEvent<Game> $event
     */
    public function __invoke(TransitionEvent $event): void
    {
        $game = $event->getSubject();

        $this->stateManager->withState($game, function (GameState $state): GameState {
            $state->currentConflict()->determineWinner();

            return $state;
        });
        $conflict = $this->stateManager->getState($game)->currentConflict();
        $this->eventDispatcher->dispatch($conflict->winnerId === null
            ? new ConflictUnwonEvent($game, $conflict->attackerSkill ?? 0, $conflict->defenderSkill ?? 0)
            : new ConflictWonEvent($game, $game->getPlayer($conflict->winnerId), $conflict->attackerSkill ?? 0, $conflict->defenderSkill ?? 0));
    }
}
