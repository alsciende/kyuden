<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\DeckRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A deck is a collection of card codes with their quantity
 * A deck has no existence inside a Game
 * A deck is used to generate the list of Cards linked to a Game.
 *
 * A deck belongs to the user who created it; a deck without owner (the starter decks) can be used by every user.
 */
#[ORM\Entity(repositoryClass: DeckRepository::class)]
#[ORM\Table('decks')]
class Deck
{
    #[ORM\Id]
    #[ORM\Column]
    private string $id;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?User $owner = null;

    /**
     * @var array<string, int> quantity by card code
     */
    #[ORM\Column(type: Types::JSON)]
    private array $cards = [];

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    /**
     * Whether the user can use the deck: their own decks and the decks without owner.
     */
    public function isUsableBy(User $user): bool
    {
        return $this->owner === null || $this->owner->getId() === $user->getId();
    }

    /**
     * @return array<string, int> quantity by card code
     */
    public function getCards(): array
    {
        return $this->cards;
    }

    /**
     * @param array<string, int> $cards quantity by card code
     *
     * @return $this
     */
    public function setCards(array $cards): static
    {
        $this->cards = $cards;

        return $this;
    }

    /**
     * @return list<string> the code of each copy of the cards
     */
    public function getCardCodes(): array
    {
        $codes = [];
        foreach ($this->cards as $code => $quantity) {
            array_push($codes, ...array_fill(0, $quantity, (string) $code));
        }

        return $codes;
    }
}
