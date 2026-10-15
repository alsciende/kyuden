<?php

declare(strict_types=1);

namespace App\View\Model;

/**
 * The log of a game as a reader sees it, from a given version: the messages handled on the game, each with the entries
 * of what it changed (automatic transitions included).
 */
final readonly class GameLogView
{
    /**
     * @param list<LoggedMessageView> $messages
     */
    public function __construct(
        public string $gameId,
        public int $version,
        public array $messages,
    ) {
    }
}
