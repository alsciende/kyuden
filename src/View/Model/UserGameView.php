<?php

declare(strict_types=1);

namespace App\View\Model;

use App\Enum\VictoryCondition;

/**
 * A game of the authenticated user, to find the games they were matched in and those they have played
 * (`GET /api/self/games`).
 */
final readonly class UserGameView
{
    /**
     * @param list<string>                   $players the users playing the game, in player order (ids of the users)
     * @param list<GameInProgressPlayerView> $seats   the players of the game, in player order: their name, clan…
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
        public array $seats,
        /** when the game ended (its last message), null while it goes on */
        public ?\DateTimeImmutable $endedAt,
        /** the player who won the game, null while it goes on or when nobody won */
        public ?string $winnerId,
        /** how the game ended, null while it goes on */
        public ?VictoryCondition $victoryCondition,
    ) {
    }
}
