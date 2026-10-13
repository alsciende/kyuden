<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\Service\VictoryService;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;

/**
 * Records the winner and the victory condition; no player has the opportunity to act anymore.
 */
#[AsTransitionListener(workflow: 'game', transition: 'end_game')]
readonly class EndGameTransitionListener
{
    public function __construct(
        private VictoryService $victoryService,
        private GameStateManager $stateManager,
    ) {
    }

    public function __invoke(TransitionEvent $event): void
    {
        /** @var Game $game */
        $game = $event->getSubject();

        $victory = $this->victoryService->findVictory($game) ?? throw new \LogicException('No player has met a victory condition.');
        $this->stateManager->withState($game, function (GameState $state) use ($victory): GameState {
            $state->winnerId = $victory->winner->getId();
            $state->victoryCondition = $victory->condition;
            $state->activePlayerIds = [];

            return $state;
        });
    }
}
