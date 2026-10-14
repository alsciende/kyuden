<?php

declare(strict_types=1);

namespace App\Entity;

use App\Exception\Data\PlayerNotFoundException;
use App\Repository\GameRepository;
use App\State\Model\StatefulInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Symfony\Component\Uid\Uuid;

/**
 * An ongoing game with players.
 *
 * Every random draw of the game (shuffles, first player, random discards, ids of new cards and stacks) goes through
 * its own random generator, seeded when the game is created: replaying the event log gives the same draws.
 */
#[ORM\Entity(repositoryClass: GameRepository::class)]
#[ORM\Table('games')]
#[ORM\HasLifecycleCallbacks]
class Game implements StatefulInterface
{
    /**
     * Final place of the `game` workflow: no action is accepted anymore.
     */
    public const string STATE_ENDED = 'game_ended';

    #[ORM\Id]
    #[ORM\Column]
    private string $id;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $currentPlace = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $state = '{}';

    /**
     * Serialized state of the random engine, written back before each flush.
     */
    #[ORM\Column(type: Types::TEXT)]
    private string $randomEngine;

    private ?Randomizer $randomizer = null;

    /**
     * @var ArrayCollection<int, Player>
     */
    #[ORM\OneToMany(mappedBy: 'game', targetEntity: Player::class, cascade: ['persist', 'remove'], fetch: 'LAZY', orphanRemoval: true)]
    #[ORM\OrderBy([
        'joinRank' => 'ASC',
    ])]
    private Collection $players;

    /**
     * @var ArrayCollection<int, PhysicalCard>
     */
    #[ORM\OneToMany(mappedBy: 'game', targetEntity: PhysicalCard::class, cascade: ['persist', 'remove'], fetch: 'LAZY', orphanRemoval: true)]
    private Collection $physicalCards;

    /**
     * @param string $seed 32 random bytes
     */
    public function __construct(string $id, string $seed)
    {
        $this->id = $id;
        $this->randomEngine = serialize(new Xoshiro256StarStar($seed));
        $this->players = new ArrayCollection();
        $this->physicalCards = new ArrayCollection();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCurrentPlace(): ?string
    {
        return $this->currentPlace;
    }

    public function setCurrentPlace(?string $currentPlace): static
    {
        $this->currentPlace = $currentPlace;

        return $this;
    }

    public function getState(): string
    {
        return $this->state;
    }

    public function setState(string $state): void
    {
        $this->state = $state;
    }

    /**
     * The random generator of the game: never draw outside of it.
     */
    public function getRandomizer(): Randomizer
    {
        if ($this->randomizer === null) {
            $engine = unserialize($this->randomEngine, [
                'allowed_classes' => [Xoshiro256StarStar::class],
            ]);
            if (! $engine instanceof Xoshiro256StarStar) {
                throw new \UnexpectedValueException("Invalid random engine for game {$this->id}.");
            }
            $this->randomizer = new Randomizer($engine);
        }

        return $this->randomizer;
    }

    /**
     * A new random id (UUID v4) drawn from the random generator of the game.
     */
    public function generateId(): string
    {
        $bytes = $this->getRandomizer()->getBytes(16);
        $bytes[6] = chr(ord($bytes[6]) & 0x0F | 0x40);
        $bytes[8] = chr(ord($bytes[8]) & 0x3F | 0x80);

        return Uuid::fromBinary($bytes)->toRfc4122();
    }

    #[ORM\PreFlush]
    public function saveRandomEngine(): void
    {
        if ($this->randomizer !== null) {
            $this->randomEngine = serialize($this->randomizer->engine);
        }
    }

    /**
     * @return Collection<int, Player> in order of arrival
     */
    public function getPlayers(): Collection
    {
        return $this->players;
    }

    public function addPlayer(Player $player): static
    {
        if (! $this->players->contains($player)) {
            $player->setJoinRank($this->players->count());
            $this->players->add($player);
            $player->setGame($this);
        }

        return $this;
    }

    public function getPlayer(string $playerId): Player
    {
        foreach ($this->players as $player) {
            if ($player->getId() === $playerId) {
                return $player;
            }
        }

        throw new PlayerNotFoundException($playerId);
    }

    /**
     * @return Collection<int, PhysicalCard>
     */
    public function getPhysicalCards(): Collection
    {
        return $this->physicalCards;
    }

    public function addCard(PhysicalCard $card): static
    {
        if (! $this->physicalCards->contains($card)) {
            $this->physicalCards->add($card);
            $card->setGame($this);
        }

        return $this;
    }

    public function removeCard(PhysicalCard $card): static
    {
        throw new \LogicException('Removing card does not exist. Delete the card instead.');
    }
}
