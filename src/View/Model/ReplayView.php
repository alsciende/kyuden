<?php

declare(strict_types=1);

namespace App\View\Model;

/**
 * Where the replay of a game that has ended stands, for the player who follows it (`/api/games/{id}/replay`): its copy
 * is read like any game (`GET /api/games/{replay_game_id}` and its log), as the player of the copy.
 */
final readonly class ReplayView
{
    public function __construct(
        /** the original game */
        public string $gameId,
        /** the copy of the game the replay rebuilds */
        public string $replayGameId,
        /** the player of the user in the copy */
        public string $playerId,
        /** the number of events replayed: the version of the copy */
        public int $version,
        /** the number of events of the original game */
        public int $total,
        /** the next action of the player, not replayed yet: the replay stopped before it when it is the next event */
        public ?ReplayActionView $next,
        /** every event of the original game has been replayed */
        public bool $ended,
    ) {
    }
}
