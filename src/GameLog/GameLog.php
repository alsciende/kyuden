<?php

declare(strict_types=1);

namespace App\GameLog;

/**
 * A domain event (App\Event) that changes the state of the game writes an entry in its log: the template names the
 * properties of the event it shows (`{player} loses {amount} honor`). The event implements GameEventInterface.
 * `params` names properties written in the entry without being shown by the template, for the clients only (the
 * cards of a deck searched, which the window of the searcher shows and the log does not list). See
 * GameLogEventListener.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class GameLog
{
    public function __construct(
        public string $template,
        /** @var list<string> */
        public array $params = [],
    ) {
    }
}
