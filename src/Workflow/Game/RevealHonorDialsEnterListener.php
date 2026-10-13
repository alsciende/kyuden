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
 * The bids are revealed (their secrecy is not enforced): every player can react, e.g. to change a bid (SetHonorBid),
 * then passes. No player has passed yet.
 */
#[AsEnterListener(workflow: 'game', place: 'reveal_honor_dials')]
readonly class RevealHonorDialsEnterListener
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
