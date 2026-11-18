<?php

declare(strict_types=1);

namespace App\Service\Tournament\Format;

use App\Entity\TournamentEntry;

/**
 * A match a format pairs at a table of a round: two players, or one for a bye.
 */
final readonly class Pairing
{
    public function __construct(
        public int $table,
        public TournamentEntry $first,
        public ?TournamentEntry $second,
    ) {
    }
}
