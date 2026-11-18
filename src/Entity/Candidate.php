<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CandidateRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A user waiting to be matched in a game. A candidate with an invitation (the host of the invitation or its guest) is
 * only matched with the other candidate of the invitation; a candidate with a match of a tournament, with the other
 * player of the match.
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

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Invitation $invitation = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?TournamentMatch $match = null;

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

    public function getInvitation(): ?Invitation
    {
        return $this->invitation;
    }

    public function setInvitation(?Invitation $invitation): static
    {
        $this->invitation = $invitation;

        return $this;
    }

    public function getMatch(): ?TournamentMatch
    {
        return $this->match;
    }

    public function setMatch(?TournamentMatch $match): static
    {
        $this->match = $match;

        return $this;
    }
}
