<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\StackType;
use App\Repository\StackRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

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

    public function addCard(PhysicalCard $card, bool $above = true): static
    {
        if (! $this->cards->contains($card)) {
            if ($above) {
                $this->cards->add($card);
            } else {
                $cards = $this->cards->toArray();
                foreach ($cards as $i => $item) {
                    $this->cards[$i + 1] = $item;
                }
                $this->cards[0] = $card;
            }
            $card->setStack($this);
        }

        return $this;
    }

    public function removeCard(PhysicalCard $card): static
    {
        if ($this->cards->removeElement($card)) {
            // set the owning side to null (unless already changed)
            if ($card->getStack() === $this) {
                $card->setStack(null);
            }
        }

        return $this;
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
