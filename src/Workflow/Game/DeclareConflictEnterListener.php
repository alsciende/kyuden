<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\GameState;
use Symfony\Component\Workflow\Attribute\AsEnterListener;
use Symfony\Component\Workflow\Event\EnterEvent;

/**
 * The next player in player order with a conflict opportunity left declares a conflict or passes (3.2): the first
 * player at the beginning of the phase, then the player after the one who used the last conflict opportunity.
 */
#[AsEnterListener(workflow: 'game', place: 'declare_conflict')]
readonly class DeclareConflictEnterListener
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
        $state = $this->stateManager->getState($game);

        $lastPlayerId = $state->lastConflictPlayerId;
        $firstPlayerId = $state->firstPlayerId ?? throw new \LogicException('The game has no first player.');
        $candidateIds = $lastPlayerId === null
            ? [$firstPlayerId, ...$state->getPlayerIdsAfter($game->getPlayer($firstPlayerId))]
            : [...$state->getPlayerIdsAfter($game->getPlayer($lastPlayerId)), $lastPlayerId];

        foreach ($candidateIds as $candidateId) {
            $candidate = $game->getPlayer($candidateId);
            if ($this->playerStateManager->getState($candidate)->getConflictOpportunities() > 0) {
                $this->stateManager->withState(
                    $game,
                    fn (GameState $state): GameState => $state->setActivePlayer($candidate),
                );

                return;
            }
        }

        throw new \LogicException('No player has a conflict opportunity left.');
    }
}
