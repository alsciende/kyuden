<?php

declare(strict_types=1);

namespace App\View\Model;

use App\Enum\TournamentFormat;
use App\Enum\TournamentStatus;

/**
 * A tournament in the list of the tournaments (`GET /api/tournaments`) and in the registrations of a user.
 */
final readonly class TournamentSummaryView
{
    public function __construct(
        public string $id,
        /** when it begins (UTC) */
        public \DateTimeImmutable $startsAt,
        /** when it locks, one hour before: no registration, change of deck or cancellation after that */
        public \DateTimeImmutable $locksAt,
        public TournamentStatus $status,
        public TournamentFormat $format,
        public TournamentUserView $organizer,
        /** how many players are registered */
        public int $entrants,
        /** how many players it takes at most */
        public int $maxEntrants,
    ) {
    }
}
