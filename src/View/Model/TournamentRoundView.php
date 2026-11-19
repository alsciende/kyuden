<?php

declare(strict_types=1);

namespace App\View\Model;

/**
 * A round of a tournament that has begun, its matches by table.
 */
final readonly class TournamentRoundView
{
    /**
     * @param list<TournamentMatchView> $matches
     */
    public function __construct(
        /** from 1 */
        public int $number,
        public array $matches,
    ) {
    }
}
