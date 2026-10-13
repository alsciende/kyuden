<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Symfony\Component\Workflow\Attribute\AsEnterListener;
use Symfony\Component\Workflow\Event\EnterEvent;

/**
 * The opportunity to act alternates between the players, starting with the first player.
 */
#[AsEnterListener(workflow: 'game', place: 'play_cards_from_provinces')]
readonly class PlayCardsFromProvincesEnterListener
{
    public function __construct(
        private GameStateManager $stateManager,
    ) {
    }

    public function __invoke(EnterEvent $event): void
    {
        /** @var Game $game */
        $game = $event->getSubject();

        $this->stateManager->withState(
            $game,
            fn (GameState $state): GameState => $state->activateFirstPlayer(),
        );
    }
}
