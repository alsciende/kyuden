<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\MatchOutcome;
use App\Enum\VictoryCondition;
use App\Repository\TournamentMatchRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A match of a round of a tournament, at its table (from 1, the order of the bracket): two players, or one for a bye.
 * Its players join the queue with it and are matched together only; the game it leads to is a standard game. Its result
 * is its winner and how it ended (MatchOutcome), with the victory condition of its game.
 */
#[ORM\Entity(repositoryClass: TournamentMatchRepository::class)]
#[ORM\Table('tournament_matches')]
#[ORM\UniqueConstraint(columns: ['tournament_id', 'round', 'table_number'])]
class TournamentMatch
{
    #[ORM\Id]
    #[ORM\GeneratedValue('SEQUENCE')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tournament $tournament;

    #[ORM\Column]
    private int $round;

    #[ORM\Column(name: 'table_number')]
    private int $table;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private TournamentEntry $first;

    /**
     * Null for a bye.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?TournamentEntry $second;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?Game $game = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?TournamentEntry $winner = null;

    #[ORM\Column(length: 16, nullable: true, enumType: MatchOutcome::class)]
    private ?MatchOutcome $outcome = null;

    #[ORM\Column(length: 32, nullable: true, enumType: VictoryCondition::class)]
    private ?VictoryCondition $victoryCondition = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endedAt = null;

    public function __construct(Tournament $tournament, int $round, int $table, TournamentEntry $first, ?TournamentEntry $second)
    {
        $this->tournament = $tournament;
        $this->round = $round;
        $this->table = $table;
        $this->first = $first;
        $this->second = $second;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTournament(): Tournament
    {
        return $this->tournament;
    }

    public function getRound(): int
    {
        return $this->round;
    }

    public function getTable(): int
    {
        return $this->table;
    }

    public function getFirst(): TournamentEntry
    {
        return $this->first;
    }

    public function getSecond(): ?TournamentEntry
    {
        return $this->second;
    }

    /**
     * @return list<TournamentEntry> its players, one for a bye
     */
    public function getEntries(): array
    {
        return $this->second instanceof TournamentEntry ? [$this->first, $this->second] : [$this->first];
    }

    public function isBye(): bool
    {
        return ! $this->second instanceof TournamentEntry;
    }

    public function findEntryOf(User $user): ?TournamentEntry
    {
        return array_find($this->getEntries(), static fn (TournamentEntry $entry): bool => $entry->getUser() === $user);
    }

    public function getOpponentOf(TournamentEntry $entry): ?TournamentEntry
    {
        return array_find($this->getEntries(), static fn (TournamentEntry $other): bool => $other !== $entry);
    }

    public function getGame(): ?Game
    {
        return $this->game;
    }

    public function setGame(Game $game): void
    {
        $this->game = $game;
    }

    public function hasResult(): bool
    {
        return $this->outcome instanceof MatchOutcome;
    }

    public function getWinner(): ?TournamentEntry
    {
        return $this->winner;
    }

    public function getOutcome(): ?MatchOutcome
    {
        return $this->outcome;
    }

    public function getVictoryCondition(): ?VictoryCondition
    {
        return $this->victoryCondition;
    }

    public function getEndedAt(): ?\DateTimeImmutable
    {
        return $this->endedAt;
    }

    /**
     * Its result: the winner given (a player of the match), or none, both players eliminated (MatchOutcome::NoResult).
     */
    public function end(?TournamentEntry $winner, MatchOutcome $outcome, \DateTimeImmutable $now, ?VictoryCondition $victoryCondition = null): void
    {
        if ($this->hasResult()) {
            throw new \LogicException("The match {$this->id} has a result already.");
        }
        if (($winner === null) !== ($outcome === MatchOutcome::NoResult) || ($winner instanceof TournamentEntry && ! in_array($winner, $this->getEntries(), true))) {
            throw new \InvalidArgumentException('A match without result has no winner, any other has one of its players.');
        }

        $this->winner = $winner;
        $this->outcome = $outcome;
        $this->victoryCondition = $victoryCondition;
        $this->endedAt = $now;
    }
}
