<?php

declare(strict_types=1);

namespace App\Event;

use App\Entity\Tournament;

/**
 * A tournament has ended, with its winner or without one (TournamentRunner). Not a game: nothing goes in a log.
 */
final readonly class TournamentEndedEvent
{
    public function __construct(
        public Tournament $tournament,
    ) {
    }
}
