<?php

declare(strict_types=1);

namespace App\Service\Tournament\Format;

use App\Entity\Tournament;
use App\Entity\TournamentEntry;
use App\Entity\TournamentMatch;
use App\Enum\TournamentFormat;
use Random\Randomizer;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * How the players of a tournament are paired, round after round (TournamentFormats finds the one of a tournament): the
 * size of the bracket and the first round, drawn when it locks, the next round once every match of a round has a
 * result, and the winner once it is over.
 */
#[AutoconfigureTag]
interface TournamentFormatInterface
{
    public function getFormat(): TournamentFormat;

    /**
     * The number of the places of the bracket for that many players (2 at least).
     */
    public function getSize(int $entrants): int;

    /**
     * The number of the rounds of a bracket of that size.
     */
    public function getRoundCount(int $size): int;

    /**
     * @param list<TournamentEntry> $entries the players, 2 at least
     *
     * @return list<Pairing> the first round, drawn with the generator of the tournament
     */
    public function pairFirstRound(array $entries, Randomizer $randomizer): array;

    /**
     * @param list<TournamentMatch> $matches the matches of the current round, every one with a result
     *
     * @return list<Pairing>|null the next round, null once the tournament is over
     */
    public function pairNextRound(Tournament $tournament, array $matches): ?array;

    /**
     * @param list<TournamentMatch> $matches the matches of the last round, every one with a result
     *
     * @return TournamentEntry|null the winner, none when no player is left
     */
    public function findWinner(Tournament $tournament, array $matches): ?TournamentEntry;
}
