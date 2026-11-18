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
     * @param list<TournamentUserView> $players
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
    ) {
    }
}
