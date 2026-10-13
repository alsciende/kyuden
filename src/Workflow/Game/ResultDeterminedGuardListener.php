<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\State\Manager\GameStateManager;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Event\GuardEvent;

/**
 * The result is determined once both players of the conflict have declared their total skill.
 */
#[AsGuardListener(workflow: 'game', transition: 'result_determined')]
readonly class ResultDeterminedGuardListener
{
    public function __construct(
        private GameStateManager $stateManager,
    ) {
    }

    public function __invoke(GuardEvent $event): void
    {
        /** @var Game $game */
        $game = $event->getSubject();

        if (! $this->stateManager->getState($game)->currentConflict()->skillsDeclared()) {
            $event->setBlocked(true, 'Both players must declare their skill.');
        }
    }
}
