<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Event\GameEndedWithoutWinnerEvent;
use App\Event\GameWonEvent;
use App\Service\Tournament\TournamentRunner;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Once a game of a tournament ends, the result of its match is recorded (TournamentRunner), in the transaction of the
 * message that ends it, and the next round may begin. A replay is no game of a tournament: it has its own id.
 */
#[AsEventListener(event: GameWonEvent::class)]
#[AsEventListener(event: GameEndedWithoutWinnerEvent::class)]
final readonly class TournamentGameEndedListener
{
    public function __construct(
        private TournamentRunner $tournamentRunner,
    ) {
    }

    public function __invoke(GameWonEvent|GameEndedWithoutWinnerEvent $event): void
    {
        $this->tournamentRunner->recordGameResult($event->getGame());
    }
}
