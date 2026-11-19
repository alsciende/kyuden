<?php

declare(strict_types=1);

namespace App\Tests\Service\Tournament\Format;

use App\Entity\Deck;
use App\Entity\Tournament;
use App\Entity\TournamentEntry;
use App\Entity\TournamentMatch;
use App\Entity\User;
use App\Enum\MatchOutcome;
use App\Enum\UserType;
use App\Service\Tournament\Format\Pairing;
use App\Service\Tournament\Format\SingleEliminationFormat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SingleEliminationFormatTest extends TestCase
{
    private const string NOW = '2026-11-18 12:00:00';

    /**
     * @param non-empty-string $id
     */
    private function account(string $id): User
    {
        $user = new User($id, $id, UserType::Human);
        $user->register($id, 'hash');

        return $user;
    }

    private function tournament(int $size): Tournament
    {
        $tournament = new Tournament('t', $this->account('organizer'), new \DateTimeImmutable('2026-11-20 20:00:00'), str_repeat('s', 32), new \DateTimeImmutable(self::NOW));
        $tournament->lock($size);

        return $tournament;
    }

    /**
     * @return list<TournamentEntry>
     */
    private function entries(Tournament $tournament, int $count): array
    {
        $deck = new Deck('deck');

        return array_map(fn (int $i): TournamentEntry => new TournamentEntry($tournament, $this->account("p{$i}"), $deck, new \DateTimeImmutable(self::NOW)), range(1, $count));
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function sizes(): iterable
    {
        yield '2 players' => [2, 2];
        yield '3 players' => [3, 4];
        yield '5 players' => [5, 8];
        yield '11 players' => [11, 16];
        yield '16 players' => [16, 16];
        yield '33 players' => [33, 64];
        yield '64 players' => [64, 64];
    }

    #[DataProvider('sizes')]
    public function testTheFirstRoundPairsEveryPlayerOnceAndNoByeMeetsABye(int $players, int $size): void
    {
        $format = new SingleEliminationFormat();
        self::assertSame($size, $format->getSize($players));

        $tournament = $this->tournament($size);
        $entries = $this->entries($tournament, $players);
        $pairings = $format->pairFirstRound($entries, $tournament->createRandomizer());

        self::assertSame(range(1, intdiv($size, 2)), array_map(static fn (Pairing $pairing): int => $pairing->table, $pairings));
        $paired = array_merge(...array_map(static fn (Pairing $pairing): array => array_filter([$pairing->first, $pairing->second]), $pairings));
        self::assertCount($players, $paired);
        foreach ($entries as $entry) {
            self::assertContains($entry, $paired);
        }
        self::assertCount($size - $players, array_filter($pairings, static fn (Pairing $pairing): bool => $pairing->second === null));
    }

    public function testTheDrawDependsOnTheSeedOnly(): void
    {
        $format = new SingleEliminationFormat();
        $tournament = $this->tournament(8);
        $entries = $this->entries($tournament, 6);

        $draw = static fn (): array => array_map(static fn (Pairing $pairing): array => [$pairing->first->getUser()->getId(), $pairing->second?->getUser()->getId()], $format->pairFirstRound($entries, $tournament->createRandomizer()));

        self::assertSame($draw(), $draw());
    }

    public function testTheNextRoundTakesTheWinnersOfTwoTablesAndAWinnerAloneHasABye(): void
    {
        $format = new SingleEliminationFormat();
        $tournament = $this->tournament(8);
        [$a, $b, $c, $d, $e, $f, $g] = $this->entries($tournament, 7);
        $now = new \DateTimeImmutable(self::NOW);
        $tournament->beginRound(1, $now);

        $matches = [
            new TournamentMatch($tournament, 1, 1, $a, $b),
            new TournamentMatch($tournament, 1, 2, $c, $d),
            new TournamentMatch($tournament, 1, 3, $e, $f),
            new TournamentMatch($tournament, 1, 4, $g, null),
        ];
        $matches[0]->end($a, MatchOutcome::Game, $now);
        $matches[1]->end($d, MatchOutcome::Forfeit, $now);
        $matches[2]->end(null, MatchOutcome::NoResult, $now);
        $matches[3]->end($g, MatchOutcome::Bye, $now);

        $pairings = $format->pairNextRound($tournament, $matches);

        self::assertNotNull($pairings);
        self::assertSame([[1, $a, $d], [2, $g, null]], array_map(static fn (Pairing $pairing): array => [$pairing->table, $pairing->first, $pairing->second], $pairings));
    }

    public function testTheTournamentIsOverAfterTheFinal(): void
    {
        $format = new SingleEliminationFormat();
        $tournament = $this->tournament(4);
        [$a, $b] = $this->entries($tournament, 2);
        $now = new \DateTimeImmutable(self::NOW);
        $tournament->beginRound(1, $now);
        $tournament->beginRound(2, $now);
        $final = new TournamentMatch($tournament, 2, 1, $a, $b);
        $final->end($b, MatchOutcome::Game, $now);

        self::assertNull($format->pairNextRound($tournament, [$final]));
        self::assertSame($b, $format->findWinner($tournament, [$final]));
    }

    public function testNobodyWinsWhenNoPlayerIsLeft(): void
    {
        $format = new SingleEliminationFormat();
        $tournament = $this->tournament(4);
        [$a, $b, $c, $d] = $this->entries($tournament, 4);
        $now = new \DateTimeImmutable(self::NOW);
        $tournament->beginRound(1, $now);
        $matches = [new TournamentMatch($tournament, 1, 1, $a, $b), new TournamentMatch($tournament, 1, 2, $c, $d)];
        $matches[0]->end(null, MatchOutcome::NoResult, $now);
        $matches[1]->end(null, MatchOutcome::NoResult, $now);

        self::assertNull($format->pairNextRound($tournament, $matches));
        self::assertNull($format->findWinner($tournament, $matches));
    }
}
