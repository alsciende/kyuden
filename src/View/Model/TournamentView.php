<?php

declare(strict_types=1);

namespace App\View\Model;

use App\Enum\TournamentFormat;
use App\Enum\TournamentStatus;

/**
 * A tournament (`GET /api/tournaments/{id}`), the same for every reader: what concerns the reader is read apart
 * (`GET /api/self/tournaments`).
 */
final readonly class TournamentView
{
    /**
     * @param list<TournamentUserView>  $players
     * @param list<TournamentRoundView> $rounds
     */
    public function __construct(
        public string $id,
        /** when it begins (UTC) */
        public \DateTimeImmutable $startsAt,
        /** when it locks, one hour before: no registration, change of deck or cancellation after that */
        public \DateTimeImmutable $locksAt,
        public TournamentStatus $status,
        public TournamentFormat $format,
        public TournamentUserView $organizer,
        /** how many players it takes at most */
        public int $maxEntrants,
        /** the players registered, in their order of registration */
        public array $players,
        /** the number of the places of its bracket, once locked (null before) */
        public ?int $size,
        /** the number of its rounds, once locked */
        public ?int $roundCount,
        /** the round being played, from 1; 0 until it begins */
        public int $currentRound,
        /** until when the players of the current round join the queue with their match, else they forfeit */
        public ?\DateTimeImmutable $joinDeadline,
        /** its winner, once ended (null without one) */
        public ?TournamentUserView $winner,
        /** the rounds that have begun, the first first: the pairings of a round are secret until it begins */
        public array $rounds,
    ) {
    }
}
