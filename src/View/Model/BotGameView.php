<?php

declare(strict_types=1);

namespace App\View\Model;

/**
 * A game against the house bot just created (`POST /api/bot-games`).
 */
final readonly class BotGameView
{
    public function __construct(
        public string $gameId,
        /** the player of the user in the game, the sender of their messages */
        public string $playerId,
        public BotGameOpponentView $opponent,
    ) {
    }
}
