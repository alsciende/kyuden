<?php

declare(strict_types=1);

namespace App\Service\Tournament\Format;

use App\Entity\Tournament;
use App\Entity\TournamentEntry;
use App\Entity\TournamentMatch;
use App\Enum\TournamentFormat;
use Random\Randomizer;

/**
 * Single elimination: a bracket of the smallest power of 2 holding the players, its empty places byes, spread over as
 * many matches (fewer byes than players: a bye never meets a bye), the players drawn at random (no seeds). The match at
 * table t of a round takes the winners of the tables 2t-1 and 2t of the previous one: a winner alone has a bye, no
 * winner at all leaves no match. The winner of the final wins the tournament.
 */
final readonly class SingleEliminationFormat implements TournamentFormatInterface
{
    #[\Override]
    public function getFormat(): TournamentFormat
    {
        return TournamentFormat::SingleElimination;
    }

    #[\Override]
    public function getSize(int $entrants): int
    {
        if ($entrants < 2) {
            throw new \InvalidArgumentException('A bracket holds 2 players at least.');
        }

        $size = 2;
        while ($size < $entrants) {
            $size *= 2;
        }

        return $size;
    }

    #[\Override]
    public function pairFirstRound(array $entries, Randomizer $randomizer): array
    {
        $size = $this->getSize(count($entries));
        $tables = range(1, intdiv($size, 2));
        $byes = $size - count($entries);
        $byeTables = $byes > 0 ? array_map(static fn (int $key): int => $tables[$key], $randomizer->pickArrayKeys($tables, $byes)) : [];
        $players = $randomizer->shuffleArray($entries);

        $pairings = [];
        foreach ($tables as $table) {
            $first = array_shift($players) ?? throw new \LogicException('Not enough players.');
            $second = in_array($table, $byeTables, true) ? null : (array_shift($players) ?? throw new \LogicException('Not enough players.'));
            $pairings[] = new Pairing($table, $first, $second);
        }

        return $pairings;
    }

    #[\Override]
    public function pairNextRound(Tournament $tournament, array $matches): ?array
    {
        $round = $tournament->getCurrentRound();
        if ($round >= $this->getRoundCount($this->getSizeOf($tournament))) {
            return null;
        }

        $winners = [];
        foreach ($matches as $match) {
            $winners[$match->getTable()] = $match->getWinner();
        }

        $pairings = [];
        $tableCount = intdiv($this->getSizeOf($tournament), 2 ** ($round + 1));
        for ($table = 1; $table <= $tableCount; ++$table) {
            $players = array_values(array_filter([$winners[2 * $table - 1] ?? null, $winners[2 * $table] ?? null]));
            if ($players !== []) {
                $pairings[] = new Pairing($table, $players[0], $players[1] ?? null);
            }
        }

        return $pairings === [] ? null : $pairings;
    }

    #[\Override]
    public function findWinner(Tournament $tournament, array $matches): ?TournamentEntry
    {
        if ($tournament->getCurrentRound() !== $this->getRoundCount($this->getSizeOf($tournament))) {
            return null;
        }

        return array_find($matches, static fn (TournamentMatch $match): bool => $match->getWinner() instanceof TournamentEntry)?->getWinner();
    }

    /**
     * The final is the last round.
     */
    #[\Override]
    public function getRoundCount(int $size): int
    {
        return (int) round(log($size, 2));
    }

    private function getSizeOf(Tournament $tournament): int
    {
        return $tournament->getSize() ?? throw new \LogicException("The tournament {$tournament->getId()} is not locked.");
    }
}
