<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\Event;

/**
 * What a step of a replay has played (Service/Replay/GameReplayer::proceed()): the events after the version it started from, up to
 * the next event of the viewer included, or to the end.
 */
final readonly class ReplayStep
{
    public function __construct(
        /**
         * The version of the replay before the step.
         */
        public int $from,
        /**
         * The version of the replay after the step.
         */
        public int $to,
        /**
         * The event of the viewer the replay stopped on (the last one played), null at the end.
         */
        public ?Event $pausedOn,
        /**
         * Every event of the original has been replayed.
         */
        public bool $ended,
    ) {
    }
}
