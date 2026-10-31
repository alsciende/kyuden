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
 * 3.2.7: the winner of the conflict claims the contested ring, in the type of the conflict. With no winner, the ring
 * returns to the unclaimed ring pool when the conflict ends. A ring a player has already claimed by an effect (Display
 * of Power) is not claimed again.
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

        $state = $this->stateManager->getState($game);
        $conflict = $state->currentConflict();
        if ($conflict->winnerId === null || $state->getRing($conflict->ring)->claimedById !== null) {
            return;
        }

        $this->stateManager->withState($game, function (GameState $state) use ($conflict): GameState {
            $state->getRing($conflict->ring)->claim($conflict->winnerId ?? throw new \LogicException('The conflict has no winner.'), $conflict->type);

            return $state;
        });
        $this->eventDispatcher->dispatch(new RingClaimedEvent($game, $game->getPlayer($conflict->winnerId), $conflict->ring));
    }
}
