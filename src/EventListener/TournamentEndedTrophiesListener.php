<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Event\TournamentEndedEvent;
use App\Service\Trophy\TournamentTrophies;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Once a tournament has ended, its players earn its trophies (TournamentTrophies), in the transaction that ends it: that
 * of the message ending the game of the final, so that the end of that game shows them at once, or that of the worker
 * (a final won by forfeit).
 */
#[AsEventListener]
readonly class TournamentEndedTrophiesListener
{
    public function __construct(
        private TournamentTrophies $tournamentTrophies,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(TournamentEndedEvent $event): void
    {
        $this->tournamentTrophies->award($event->tournament, $this->clock->now());
    }
}
