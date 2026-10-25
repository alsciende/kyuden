<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\Event\RingClaimedEvent;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;

/**
 * 3.2.7: the winner of the conflict claims the contested ring. With no winner, the ring returns to the unclaimed ring
 * pool when the conflict ends.
 */
#[AsTransitionListener(workflow: 'game', transition: 'ring_claimed')]
readonly class RingClaimedTransitionListener
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
            $conflict = $state->currentConflict();
            if ($conflict->winnerId !== null) {
                $state->getRing($conflict->ring)->claimedById = $conflict->winnerId;
            }

            return $state;
        });
        $conflict = $this->stateManager->getState($game)->currentConflict();
        if ($conflict->winnerId !== null) {
            $this->eventDispatcher->dispatch(new RingClaimedEvent($game, $game->getPlayer($conflict->winnerId), $conflict->ring));
        }
    }
}
