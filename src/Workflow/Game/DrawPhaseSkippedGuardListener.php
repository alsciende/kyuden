<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\State\Manager\GameStateManager;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Event\GuardEvent;

/**
 * The draw phase begins, unless an interrupt has cancelled its beginning (CancelImminentTrigger: Trading on the Sand
 * Road, "skip the draw phase"): it is then skipped, the game going on with the conflict phase.
 */
#[AsGuardListener(workflow: 'game', transition: 'draw_phase_began')]
#[AsGuardListener(workflow: 'game', transition: 'draw_phase_skipped')]
readonly class DrawPhaseSkippedGuardListener
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
        $skipped = $this->stateManager->getState($event->getSubject())->interruptedTransition?->cancelledFor('draw_phase_began') ?? false;
        if ($event->getTransition()->getName() === 'draw_phase_skipped' ? ! $skipped : $skipped) {
            $event->setBlocked(true, $skipped ? 'The draw phase is skipped.' : 'The draw phase is not skipped.');
        }
    }
}
