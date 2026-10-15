<?php

declare(strict_types=1);

namespace App\GameLog;

/**
 * A domain event (App\Event) that changes the state of the game writes an entry in its log: the template names the
 * properties of the event it shows (`{player} loses {amount} honor`). The event implements GameEventInterface.
 * See GameLogEventListener.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class GameLog
{
    public function __construct(
        public string $template,
    ) {
    }
}
