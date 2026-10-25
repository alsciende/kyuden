<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\EventRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * An event changed the state of the game
 * Its name is the class of the message that triggered this change
 * Its payload is the content of the message.
 * Its version is the version of the game after this change: the events of a game are numbered from 1.
 */
#[ORM\Entity(repositoryClass: EventRepository::class)]
#[ORM\Table('events')]
#[ORM\UniqueConstraint(columns: ['game_id', 'version'])]
class Event
{
    #[ORM\Id]
    #[ORM\GeneratedValue('SEQUENCE')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Game::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Game $game;

    #[ORM\Column]
    private int $version;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $createdAt = null;

    #[ORM\Column(type: 'jsonb')]
    private string $payload;

    public function __construct(Game $game, int $version, string $name, string $payload)
    {
        $this->game = $game;
        $this->version = $version;
        $this->name = $name;
        $this->payload = $payload;
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

        return $this;
    }

    public function getGame(): Game
    {
        return $this->game;
    }

    public function getVersion(): int
    {
        return $this->version;
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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = \DateTime::createFromInterface($createdAt);

        return $this;
    }

    public function getPayload(): string
    {
        return $this->payload;
    }

    public function setPayload(string $payload): static
    {
        $this->payload = $payload;

        return $this;
    }
}
