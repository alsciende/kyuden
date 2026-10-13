<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use Symfony\Component\Workflow\Attribute\AsEnterListener;
use Symfony\Component\Workflow\Event\EnterEvent;

/**
 * 3.4.2: the winner of the glory count claims the Imperial Favor (ClaimImperialFavor); on a tie, nobody does
 * (`imperial_favor_unclaimed`).
 */
#[AsEnterListener(workflow: 'game', place: 'claim_imperial_favor')]
readonly class ClaimImperialFavorEnterListener
{
    public function __construct(
        private GameStateManager $stateManager,
    ) {
    }

    public function __invoke(EnterEvent $event): void
    {
        /** @var Game $game */
        $game = $event->getSubject();

        $this->stateManager->withState($game, function (GameState $state): GameState {
            $state->activePlayerIds = $state->gloryCountWinnerId === null ? [] : [$state->gloryCountWinnerId];

            return $state;
        });
    }
}
