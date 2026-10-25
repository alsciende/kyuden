<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Symfony\Component\Workflow\Attribute\AsEnterListener;
use Symfony\Component\Workflow\Event\EnterEvent;

/**
 * Players collect fate in player order.
 */
#[AsEnterListener(workflow: 'game', place: 'collect_fate')]
readonly class CollectFateEnterListener
{
    public function __construct(
        private GameStateManager $stateManager,
    ) {
    }

    /**
     * @param EnterEvent<Game> $event
     */
    public function __invoke(EnterEvent $event): void
    {
        $game = $event->getSubject();

        $this->stateManager->withState(
            $game,
            fn (GameState $state): GameState => $state->activateFirstPlayer(),
        );
    }
}
