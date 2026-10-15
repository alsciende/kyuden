<?php

declare(strict_types=1);

namespace App\GameLog;

use App\Entity\Game;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Workflow\Event\LeaveEvent;

/**
 * Writes the log entry of every transition of the `game` workflow, from its `log` metadata (required, without params:
 * what the transition changes is logged by its listeners). Written when the game leaves its place, before the
 * listeners of the transition and of the place it enters.
 */
#[AsEventListener(event: 'workflow.game.leave', priority: 100)]
final readonly class GameTransitionLogListener
{
    public function __construct(
        private GameLogger $gameLogger,
    ) {
    }

    public function __invoke(LeaveEvent $event): void
    {
        $game = $event->getSubject();
        $transition = $event->getTransition();
        if (! $game instanceof Game || $transition === null) {
            return;
        }

        $template = $event->getMetadata('log', $transition);
        if (! is_string($template)) {
            throw new \LogicException(sprintf("Game transition %s must give its 'log' metadata.", $transition->getName()));
        }

        $this->gameLogger->log($game, $template);
    }
}
