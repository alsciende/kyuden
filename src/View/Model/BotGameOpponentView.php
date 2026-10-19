<?php

declare(strict_types=1);

namespace App\View\Model;

/**
 * The house bot in a game against it: its user and the starter deck it plays.
 */
final readonly class BotGameOpponentView
{
    public function __construct(
        public string $userId,
        public string $name,
        public string $deckId,
    ) {
    }
}
