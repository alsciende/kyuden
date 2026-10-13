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
 * Steps where the players act simultaneously, e.g. to react to a framework step: every player has the opportunity to
 * act until they pass. No player has passed yet.
 * When the honor dials are revealed (their secrecy is not enforced), players can change a bid (SetHonorBid).
 */
#[AsEnterListener(workflow: 'game', place: 'reveal_honor_dials')]
#[AsEnterListener(workflow: 'game', place: 'react_to_conflict_declaration')]
#[AsEnterListener(workflow: 'game', place: 'react_to_conflict_result')]
#[AsEnterListener(workflow: 'game', place: 'react_to_broken_province')]
#[AsEnterListener(workflow: 'game', place: 'react_to_ring_claim')]
readonly class AllPlayersOpportunityEnterListener
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
            fn (GameState $state): GameState => $state->activateAllPlayers(),
        );
        foreach ($game->getPlayers() as $player) {
            $this->playerStateManager->withState(
                $player,
                fn (PlayerState $state): PlayerState => $state->setPassed(false),
            );
        }
    }
}
