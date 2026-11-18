<?php

declare(strict_types=1);

namespace App\View\Model;

use App\Enum\MatchOutcome;
use App\Enum\VictoryCondition;

/**
 * A match of a round that has begun, in the view of a tournament.
 */
final readonly class TournamentMatchView
{
    /**
     * @param list<TournamentUserView> $players  its two players, one for a bye
     * @param list<string>             $waiting the ids of the users of its players waiting in the queue with it
     */
    public function __construct(
        /** the id to join the queue with (`POST /api/queue`, `{"match_id": ...}`) */
        public int $id,
        /** its table in the round, from 1: the order of the bracket */
        public int $table,
        public array $players,
        /** the game of the match, to watch it, null before it begins (or without a game) */
        public ?string $gameId,
        /** the user id of its winner, null without a result (yet) */
        public ?string $winnerUserId,
        /** how it ended, null while it is played */
        public ?MatchOutcome $outcome,
        /** how its game ended (outcome `game`) */
        public ?VictoryCondition $victoryCondition,
        public array $waiting,
    ) {
    }
}
