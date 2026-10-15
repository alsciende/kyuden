<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\Event\HonorLostEvent;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\PlayerState;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;

/**
 * 3.2.4: when the attacking player won with no defending character, the defending player loses 1 honor.
 */
#[AsTransitionListener(workflow: 'game', transition: 'unopposed_applied')]
readonly class UnopposedAppliedTransitionListener
{
    public const int UNOPPOSED_HONOR_LOSS = 1;

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
        $conflict = $this->stateManager->getState($game)->currentConflict();

        if ($conflict->attackerWon() && $conflict->defenderIds === []) {
            $this->playerStateManager->withState(
                $game->getPlayer($conflict->defendingPlayerId),
                fn (PlayerState $state): PlayerState => $state->decrementHonor(self::UNOPPOSED_HONOR_LOSS),
            );
            $this->eventDispatcher->dispatch(new HonorLostEvent($game, $game->getPlayer($conflict->defendingPlayerId), self::UNOPPOSED_HONOR_LOSS));
        }
    }
}
