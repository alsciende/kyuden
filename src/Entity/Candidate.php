<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CandidateRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A user waiting to be matched in a game.
 */
#[ORM\Entity(repositoryClass: CandidateRepository::class)]
#[ORM\Table('candidates')]
class Candidate
{
    #[ORM\Id]
    #[ORM\GeneratedValue('SEQUENCE')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private Deck $deck;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getDeck(): Deck
    {
        return $this->deck;
    }

    public function setDeck(Deck $deck): static
    {
        $this->deck = $deck;

        return $this;
    }
}
