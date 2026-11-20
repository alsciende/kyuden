<?php

declare(strict_types=1);

namespace App\Workflow\Game;

use App\Entity\Game;
use App\Enum\Phase;
use App\Event\PhaseBeganEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Workflow\Attribute\AsCompletedListener;
use Symfony\Component\Workflow\Event\CompletedEvent;

/**
 * Once a phase has begun, the cards reacting to it may be triggered (PhaseBeganEvent).
 */
#[AsCompletedListener(workflow: 'game', transition: 'dynasty_phase_began')]
#[AsCompletedListener(workflow: 'game', transition: 'draw_phase_began')]
#[AsCompletedListener(workflow: 'game', transition: 'conflict_phase_began')]
#[AsCompletedListener(workflow: 'game', transition: 'fate_phase_began')]
readonly class PhaseBeganCompletedListener
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @param CompletedEvent<Game> $event
     */
    public function __invoke(CompletedEvent $event): void
    {
        $phase = Phase::from(substr((string) $event->getTransition()?->getName(), 0, -\strlen('_phase_began')));

        $this->eventDispatcher->dispatch(new PhaseBeganEvent($event->getSubject(), $phase));
    }
}
