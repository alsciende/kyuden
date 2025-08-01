<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\CardTypes\StrongholdCard;
use App\Enum\StackType;
use App\Exception\Rules\DeckConstructionException;
use App\Repository\PlayerRepository;
use App\State\Model\StatefulInterface;
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
class Player implements StatefulInterface
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
    private string $userId;

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
    private Deck $deck;

    public function __construct(string $id)
    {
        $this->id = $id;
        $this->physicalCards = new ArrayCollection();
        $this->stacks = new ArrayCollection();
    }

    /**
     * @var Collection<int, Stack>
     */
    #[ORM\OneToMany(mappedBy: 'player', targetEntity: Stack::class)]
    private Collection $stacks;

    /**
     * @return Collection<int, PhysicalCard>
     */
    public function getCardsByCurrentPlace(string $place): Collection
    {
        return $this->getPhysicalCards()->filter(
            fn (PhysicalCard $card): bool => $card->getCurrentPlace() === $place
        );
    }

    /**
     * @param class-string $className
     *
     * @return Collection<int, PhysicalCard>
     */
    public function getCardsByClass(string $className): Collection
    {
        return $this->getPhysicalCards()->filter(
            fn (PhysicalCard $card): bool => $card instanceof $className
        );
    }

    public function getStronghold(): PhysicalCard
    {
        $strongholds = $this->getCardsByClass(StrongholdCard::class);

        if ($strongholds->count() > 1) {
            throw new DeckConstructionException('Deck contains more than one Stronghold');
        }

        $stronghold = $strongholds->first();

        if ($stronghold === false) {
            throw new DeckConstructionException('Deck contains no Strongholds');
        }

        return $stronghold;
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

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): static
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
        throw new \LogicException('Removing card does not exist. Delete the card instead.');
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

    /**
     * @return Collection<int, Stack>
     */
    public function getStacks(?StackType $stackType = null): Collection
    {
        if ($stackType === null) {
            return $this->stacks;
        }

        return $this->stacks->filter(fn (Stack $stack): bool => $stack->getType() === $stackType);
    }

    public function getUniqueStack(StackType $stackType): Stack
    {
        if (! $stackType->isUnique()) {
            throw new \LogicException('Stack type must be unique');
        }

        foreach ($this->stacks as $stack) {
            if ($stack->getType() === $stackType) {
                return $stack;
            }
        }

        throw new \LogicException('Stack not found for type: ' . $stackType->value);
    }
}
