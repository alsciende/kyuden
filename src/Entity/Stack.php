<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\StackType;
use App\Repository\StackRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\OrderBy;

#[ORM\Entity(repositoryClass: StackRepository::class)]
class Stack
{
    #[ORM\Id]
    #[ORM\Column]
    private string $id;

    /**
     * @var Collection<int, PhysicalCard>
     */
    #[ORM\OneToMany(mappedBy: 'stack', targetEntity: PhysicalCard::class)]
    #[OrderBy([
        'position' => 'ASC',
    ])]
    private Collection $cards;

    #[ORM\ManyToOne(inversedBy: 'stacks')]
    #[ORM\JoinColumn(nullable: false)]
    private Player $player;

    #[ORM\Column(enumType: StackType::class)]
    private StackType $type;

    public function __construct(string $id, Player $player, StackType $type)
    {
        $this->id = $id;
        $this->player = $player;
        $this->player->getStacks()->add($this);
        $this->type = $type;
        $this->cards = new ArrayCollection();
    }

    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return Collection<int, PhysicalCard>
     */
    public function getCards(): Collection
    {
        return $this->cards;
    }

    /**
     * @param array<PhysicalCard> $cards
     */
    public function addCards(array $cards): void
    {
        foreach ($cards as $card) {
            $this->addCard($card);
        }
    }

    /**
     * @param \Closure(PhysicalCard): bool $closure
     *
     * @return $this
     */
    public function addPlayerCards(Player $player, \Closure $closure): static
    {
        $this->addCards(
            array_filter(
                $player->getPhysicalCards()->toArray(),
                $closure
            )
        );

        return $this;
    }

    public function addCard(PhysicalCard $card, bool $above = true): static
    {
        if (! $this->cards->contains($card)) {
            $this->cards->add($card);
            $card->setStack($this);

            if ($above) {
                $card->setPosition($this->cards->count());
            } else {
                $card->setPosition(-1);
            }
            $this->repositionCards();
        }

        return $this;
    }

    public function moveCard(PhysicalCard $card, bool $above = true): static
    {
        if ($this->cards->contains($card)) {
            if ($above) {
                $card->setPosition($this->cards->count());
            } else {
                $card->setPosition(-1);
            }
            $this->repositionCards();
        }

        return $this;
    }

    private function repositionCards(): void
    {
        $cards = $this->cards->getValues();
        usort($cards, fn (PhysicalCard $a, PhysicalCard $b): int => $a->getPosition() <=> $b->getPosition());
        foreach ($cards as $i => $card) {
            $card->setPosition($i);
        }
    }

    public function getPlayer(): Player
    {
        return $this->player;
    }

    public function getType(): StackType
    {
        return $this->type;
    }
}
