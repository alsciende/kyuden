<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
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
    ) {
    }

    public function __invoke(TransitionEvent $event): void
    {
        /** @var Game $game */
        $game = $event->getSubject();

        $this->stateManager->withState($game, function (GameState $state) use ($game): GameState {
            $firstPlayerId = $state->firstPlayerId ?? throw new \LogicException('The game has no first player.');

            return $state->setFirstPlayer($game->getPlayer($state->getNextPlayerId($game->getPlayer($firstPlayerId))));
        });
    }
}
