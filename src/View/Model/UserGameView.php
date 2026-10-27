<?php

declare(strict_types=1);

namespace App\View\Model;

/**
 * A game of the authenticated user, to find the games they were matched in (`GET /api/self/games`).
 */
final readonly class UserGameView
{
    /**
     * @param list<string> $players the users playing the game, in player order (ids of the users)
     */
    public function __construct(
        public string $id,
        /** the player of the user in the game, the sender of their messages */
        public string $playerId,
        /** the number of messages handled on the game, the ETag of its view */
        public int $version,
        public bool $ended,
        /** a game of an invitation, not ranked */
        public bool $friendly,
        public array $players,
    ) {
    }
}
