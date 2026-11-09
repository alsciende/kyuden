<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\Event;

/**
 * What a step of a replay has played (Service/Replay/GameReplayer::proceed()): the events after the version it
 * started from, up to the next event of the viewer excluded (or a version given), or to the end.
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
         * The next event of the viewer, not replayed yet: the action the replay stopped before, when it is the next
         * event; null when the viewer sends no other.
         */
        public ?Event $next,
        /**
         * Every event of the original has been replayed.
         */
        public bool $ended,
    ) {
    }
}
