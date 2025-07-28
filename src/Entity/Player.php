<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\CardTypes\ConflictCard;
use App\Entity\CardTypes\DynastyCard;
use App\Enum\Type;
use App\Exception\Rules\DeckConstructionException;
use App\Repository\PlayerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\OrderBy;

/**
 * This is one of the 2 players in a game.
 */
#[ORM\Entity(repositoryClass: PlayerRepository::class)]
#[ORM\Table('players')]
#[ORM\HasLifecycleCallbacks]
class Player
{
    #[ORM\Id]
    #[ORM\Column]
    private string $id;

    #[ORM\Column(type: Types::TEXT)]
    private string $state = '{}';

    #[ORM\ManyToOne(inversedBy: 'players')]
    #[ORM\JoinColumn(nullable: false)]
    private Game $game;

    #[ORM\Column(length: 255)]
    private ?string $userId = null;

    /**
     * @var ArrayCollection<int, PhysicalCard>
     */
    #[ORM\OneToMany(mappedBy: 'player', targetEntity: PhysicalCard::class, fetch: 'LAZY', orphanRemoval: true)]
    #[OrderBy([
        'position' => 'ASC',
    ])]
    private Collection $physicalCards;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Deck $deck = null;

    public function __construct(string $id)
    {
        $this->id = $id;
        $this->physicalCards = new ArrayCollection();
    }

    /**
     * @var array<string, list<PhysicalCard>>
     */
    private array $cardsByPlaceCollection = [];

    /**
     * @var array<string, list<PhysicalCard>>
     */
    private array $cardsByTypeCollection = [];

    #[ORM\PostLoad]
    public function onPostLoad(): void
    {
        $this->resetCardGroups();
    }

    public function resetCardGroups(): void
    {
        $this->cardsByPlaceCollection = [];
        $this->cardsByTypeCollection = [];
        foreach ($this->physicalCards as $card) {
            $place = $card->getCurrentPlace();
            if ($place === null) {
                throw new \LogicException('Uninitialized card cannot be grouped');
            }

            if (! array_key_exists($place, $this->cardsByPlaceCollection)) {
                $this->cardsByPlaceCollection[$place] = [];
            }

            $this->cardsByPlaceCollection[$place][] = $card;

            $type = $card->getLogicalCard()->getType()->value;

            if (! array_key_exists($type, $this->cardsByTypeCollection)) {
                $this->cardsByTypeCollection[$type] = [];
            }

            $this->cardsByTypeCollection[$type][] = $card;
        }

        usort(
            $this->cardsByPlaceCollection[DynastyCard::STATE_DRAW_DECK],
            fn (PhysicalCard $card): int => $card->getPosition() ?? 0,
        );
        usort(
            $this->cardsByPlaceCollection[ConflictCard::STATE_DRAW_DECK],
            fn (PhysicalCard $card): int => $card->getPosition() ?? 0,
        );
    }

    /**
     * @return array<string, list<PhysicalCard>>
     */
    public function getCardsByPlaceCollection(): array
    {
        return $this->cardsByPlaceCollection;
    }

    /**
     * @return list<PhysicalCard>
     */
    public function getCardsByPlace(string $place): array
    {
        if (! array_key_exists($place, $this->cardsByPlaceCollection)) {
            return [];
        }

        return $this->cardsByPlaceCollection[$place];
    }

    /**
     * @return array<string, list<PhysicalCard>>
     */
    public function getCardsByTypeCollection(): array
    {
        return $this->cardsByTypeCollection;
    }

    /**
     * @return list<PhysicalCard>
     */
    public function getCardsByType(Type $type): array
    {
        if (! array_key_exists($type->value, $this->cardsByTypeCollection)) {
            return [];
        }

        return $this->cardsByTypeCollection[$type->value];
    }

    public function getStronghold(): PhysicalCard
    {
        $strongholds = $this->getCardsByType(Type::STRONGHOLD);

        if (count($strongholds) !== 1) {
            throw new DeckConstructionException('Deck must contain exactly one Stronghold');
        }

        return $strongholds[0];
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(string $state): void
    {
        $this->state = $state;
    }

    public function getGame(): Game
    {
        return $this->game;
    }

    public function setGame(Game $game): static
    {
        $this->game = $game;

        return $this;
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function setUserId(?string $userId): static
    {
        $this->userId = $userId;

        return $this;
    }

    /**
     * @return Collection<int, PhysicalCard>
     */
    public function getPhysicalCards(): Collection
    {
        return $this->physicalCards;
    }

    /**
     * @param ArrayCollection<int, PhysicalCard> $physicalCards
     */
    public function setPhysicalCards(ArrayCollection $physicalCards): void
    {
        $this->physicalCards = $physicalCards;
    }

    public function addCard(PhysicalCard $card): static
    {
        if (! $this->physicalCards->contains($card)) {
            $this->physicalCards->add($card);
            $card->setPlayer($this);
        }

        return $this;
    }

    public function removeCard(PhysicalCard $card): static
    {
        if ($this->physicalCards->removeElement($card)) {
            // set the owning side to null (unless already changed)
            if ($card->getPlayer() === $this) {
                $card->setPlayer(null);
            }
        }

        return $this;
    }

    public function getDeck(): ?Deck
    {
        return $this->deck;
    }

    public function setDeck(?Deck $deck): static
    {
        $this->deck = $deck;

        return $this;
    }
}
