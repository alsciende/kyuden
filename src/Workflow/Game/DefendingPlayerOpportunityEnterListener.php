<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\GameState;
use App\State\Model\PlayerState;
use Symfony\Component\Workflow\Attribute\AsEnterListener;
use Symfony\Component\Workflow\Event\EnterEvent;

/**
 * The defending player declares defenders (3.2.1), then has the first opportunity to act in the conflict action
 * window (3.2.2), where the opportunity alternates between the players until they have passed consecutively. No
 * player has passed yet.
 */
#[AsEnterListener(workflow: 'game', place: 'declare_defenders')]
#[AsEnterListener(workflow: 'game', place: 'conflict_action_window')]
readonly class DefendingPlayerOpportunityEnterListener
{
    public function __construct(
        private GameStateManager $stateManager,
        private PlayerStateManager $playerStateManager,
    ) {
    }

    public function __invoke(EnterEvent $event): void
    {
        /** @var Game $game */
        $game = $event->getSubject();

        $this->stateManager->withState(
            $game,
            fn (GameState $state): GameState => $state->setActivePlayer($game->getPlayer($state->currentConflict()->defendingPlayerId)),
        );
        foreach ($game->getPlayers() as $player) {
            $this->playerStateManager->withState(
                $player,
                fn (PlayerState $state): PlayerState => $state->setPassed(false),
            );
        }
    }
}
