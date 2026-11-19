<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\TournamentEntryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A player registered in a tournament, with the deck they play all of it with (changed until it locks). A user
 * registers once in a tournament. Once the tournament is under way, a player is in play until they lose a match.
 */
#[ORM\Entity(repositoryClass: TournamentEntryRepository::class)]
#[ORM\Table('tournament_entries')]
#[ORM\UniqueConstraint(columns: ['tournament_id', 'user_id'])]
class TournamentEntry
{
    #[ORM\Id]
    #[ORM\GeneratedValue('SEQUENCE')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Tournament $tournament;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Deck $deck;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $registeredAt;

    #[ORM\Column]
    private bool $eliminated = false;

    public function __construct(Tournament $tournament, User $user, Deck $deck, \DateTimeImmutable $now)
    {
        if (! $user->canTakePartInTournaments()) {
            throw new \InvalidArgumentException('Only an account registers in a tournament.');
        }

        $this->tournament = $tournament;
        $this->user = $user;
        $this->deck = $deck;
        $this->registeredAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTournament(): Tournament
    {
        return $this->tournament;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getDeck(): Deck
    {
        return $this->deck;
    }

    public function changeDeck(Deck $deck): void
    {
        $this->deck = $deck;
    }

    public function getRegisteredAt(): \DateTimeImmutable
    {
        return $this->registeredAt;
    }

    public function isEliminated(): bool
    {
        return $this->eliminated;
    }

    public function eliminate(): void
    {
        $this->eliminated = true;
    }
}
