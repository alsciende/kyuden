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
 * Steps resolved in player order: the opportunity to act alternates between the players, starting with the first
 * player. No player has passed yet.
 */
#[AsEnterListener(workflow: 'game', place: 'play_cards_from_provinces')]
#[AsEnterListener(workflow: 'game', place: 'draw_phase_action_window')]
#[AsEnterListener(workflow: 'game', place: 'conflict_phase_action_window')]
readonly class FirstPlayerOpportunityEnterListener
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
            fn (GameState $state): GameState => $state->activateFirstPlayer(),
        );
        foreach ($game->getPlayers() as $player) {
            $this->playerStateManager->withState(
                $player,
                fn (PlayerState $state): PlayerState => $state->setPassed(false),
            );
        }
    }
}
