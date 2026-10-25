<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\Event\FirstPlayerChangedEvent;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;

/**
 * 4.8: the next player in player order becomes the first player.
 */
#[AsTransitionListener(workflow: 'game', transition: 'first_player_token_passed')]
readonly class FirstPlayerTokenPassedTransitionListener
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
        $firstPlayerId = $state->firstPlayerId ?? throw new \LogicException('The game has no first player.');
        $nextPlayer = $game->getPlayer($state->getNextPlayerId($game->getPlayer($firstPlayerId)));
        $this->stateManager->withState($game, fn (GameState $state): GameState => $state->setFirstPlayer($nextPlayer));
        $this->eventDispatcher->dispatch(new FirstPlayerChangedEvent($game, $nextPlayer));
    }
}
