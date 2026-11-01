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
 *
 * Its content hash identifies its cards whatever its name, so that identical decks are found. A deck deleted by its
 * owner once played in a game stays for the players (deletedAt): nobody can use it any more.
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

    /**
     * SHA-256 of the cards (hashCards()), kept in sync with them
     */
    #[ORM\Column(length: 64)]
    private string $contentHash;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    public function __construct(string $id)
    {
        $this->id = $id;
        $this->contentHash = self::hashCards([]);
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
        return ! $this->isDeleted() && ($this->owner === null || $this->owner->getId() === $user->getId());
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt instanceof \DateTimeImmutable;
    }

    public function delete(\DateTimeImmutable $deletedAt): void
    {
        $this->deletedAt = $deletedAt;
    }

    public function restore(): void
    {
        $this->deletedAt = null;
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
        $this->contentHash = self::hashCards($cards);

        return $this;
    }

    public function getContentHash(): string
    {
        return $this->contentHash;
    }

    /**
     * The hash of the content of a deck: the SHA-256 of a line `code:quantity` per card, sorted by code.
     *
     * @param array<string, int> $cards quantity by card code
     */
    public static function hashCards(array $cards): string
    {
        ksort($cards, \SORT_STRING);
        $lines = [];
        foreach ($cards as $code => $quantity) {
            $lines[] = "{$code}:{$quantity}";
        }

        return hash('sha256', implode("\n", $lines));
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
