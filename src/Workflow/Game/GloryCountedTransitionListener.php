<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\Event\GloryCountWonEvent;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\GameState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;

/**
 * 3.4.1: each player adds 1 per claimed ring to their declared glory count; the player with the highest total wins the
 * glory count, nobody on a tie.
 */
#[AsTransitionListener(workflow: 'game', transition: 'glory_counted')]
readonly class GloryCountedTransitionListener
{
    public function __construct(
        private GameStateManager $stateManager,
        private PlayerStateManager $playerStateManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function __invoke(TransitionEvent $event): void
    {
        /** @var Game $game */
        $game = $event->getSubject();
        $state = $this->stateManager->getState($game);

        $totals = [];
        foreach ($game->getPlayers() as $player) {
            $totals[$player->getId()] = ($this->playerStateManager->getState($player)->getGloryCount() ?? 0) + $state->countClaimedRings($player);
        }
        arsort($totals);
        $highest = array_slice($totals, 0, 2, true);
        $winnerId = count($highest) === 1 || array_values($highest)[0] > array_values($highest)[1] ? (string) array_key_first($highest) : null;

        $this->stateManager->withState($game, function (GameState $state) use ($winnerId): GameState {
            $state->gloryCountWinnerId = $winnerId;

            return $state;
        });
        if ($winnerId !== null) {
            $this->eventDispatcher->dispatch(new GloryCountWonEvent($game, $game->getPlayer($winnerId)));
        }
    }
}
