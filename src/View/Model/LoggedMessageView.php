<?php

declare(strict_types=1);

namespace App\View\Model;

/**
 * A message handled on a game, as its log shows it: its type and its sender, not its payload, which may hold secret
 * information (the payload of an event of the game is never shown).
 */
final readonly class LoggedMessageView
{
    /**
     * @param list<LogEntryView> $entries
     */
    public function __construct(
        public int $version,
        public string $type,
        public ?string $playerId,
        public array $entries,
    ) {
    }
}
