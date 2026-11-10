<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\State\Manager\GameStateManager;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Event\GuardEvent;

/**
 * A mulligan step ends once every player has mulliganed (or kept their cards): the last one leaves nobody active.
 */
#[AsGuardListener(workflow: 'game', transition: 'dynasty_mulligan_ended')]
#[AsGuardListener(workflow: 'game', transition: 'conflict_mulligan_ended')]
readonly class MulliganEndedGuardListener
{
    public function __construct(
        private GameStateManager $stateManager,
    ) {
    }

    /**
     * @param GuardEvent<Game> $event
     */
    public function __invoke(GuardEvent $event): void
    {
        if ($this->stateManager->getState($event->getSubject())->countActivePlayers() > 0) {
            $event->setBlocked(true, 'A player has not mulliganed yet.');
        }
    }
}
