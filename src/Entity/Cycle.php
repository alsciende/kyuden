<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CycleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\Cache;
use Symfony\Component\Serializer\Attribute\Ignore;

/**
 * A cycle of packs (the core set, the clan packs, each cycle of the chapter packs…).
 */
#[ORM\Entity(repositoryClass: CycleRepository::class)]
#[Cache(usage: 'READ_ONLY')]
#[ORM\Table('cycles')]
class Cycle implements \Stringable
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'NONE')]
    #[ORM\Column(type: Types::STRING)]
    private string $id;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $name;

    #[ORM\Column(type: Types::INTEGER)]
    private int $position;

    #[ORM\Column(type: Types::INTEGER)]
    private int $size;

    /**
     * @var Collection<int, Pack>
     */
    #[ORM\OneToMany(targetEntity: Pack::class, mappedBy: 'cycle')]
    #[ORM\OrderBy(['position' => 'ASC'])]
    #[Ignore]
    private Collection $packs;

    public function __construct()
    {
        $this->packs = new ArrayCollection();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): static
    {
        $this->position = $position;

        return $this;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): static
    {
        $this->size = $size;

        return $this;
    }

    /**
     * @return Collection<int, Pack>
     */
    public function getPacks(): Collection
    {
        return $this->packs;
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->getName();
    }
}
