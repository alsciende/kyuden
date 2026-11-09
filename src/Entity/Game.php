<?php

declare(strict_types=1);

namespace App\Entity;

use App\Exception\Data\PlayerNotFoundException;
use App\Exception\Data\WorkflowException;
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
#[ORM\UniqueConstraint(name: 'replay_of_user', columns: ['replay_of_id', 'replay_user_id'])]
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
     * Number of messages handled on the game: the version of its state, and the sequence number of its events.
     */
    #[ORM\Column]
    private int $version = 0;

    /**
     * When the last message was handled on the game (its version changed); null before the first one.
     */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * Serialized state of the random engine, written back before each flush.
     */
    #[ORM\Column(type: Types::TEXT)]
    private string $randomEngine;

    private ?Randomizer $randomizer = null;

    /**
     * A friendly game (an invitation to it): not ranked.
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $friendly;

    /**
     * The game this one replays (Service/Replay/GameReplayer): a copy rebuilt from the events of a game that has ended,
     * for one of its players only, read only and listed nowhere; null for a game played.
     */
    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Game $replayOf = null;

    /**
     * The user who follows the replay, a player of the original game.
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?User $replayUser = null;

    /**
     * @var ArrayCollection<int, Player>
     */
    #[ORM\OneToMany(mappedBy: 'game', targetEntity: Player::class, cascade: ['persist', 'remove'], fetch: 'LAZY', orphanRemoval: true)]
    #[ORM\OrderBy([
        'joinRank' => \SortDirection::Ascending,
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
    public function __construct(string $id, string $seed, bool $friendly = false)
    {
        $this->id = $id;
        $this->friendly = $friendly;
        $this->randomEngine = serialize(new Xoshiro256StarStar($seed));
        $this->players = new ArrayCollection();
        $this->physicalCards = new ArrayCollection();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function isFriendly(): bool
    {
        return $this->friendly;
    }

    /**
     * Makes the game the replay of another one, followed by the user given.
     */
    public function markAsReplay(self $original, User $user): void
    {
        $this->replayOf = $original;
        $this->replayUser = $user;
    }

    public function isReplay(): bool
    {
        return $this->replayOf instanceof self;
    }

    public function getReplayOf(): ?self
    {
        return $this->replayOf;
    }

    public function getReplayUser(): ?User
    {
        return $this->replayUser;
    }

    /**
     * A replay exists for its user only: anyone else is told it does not exist.
     */
    public function isHiddenFrom(?string $userId): bool
    {
        return $this->replayUser instanceof User && $this->replayUser->getId() !== $userId;
    }

    public function getCurrentPlace(): ?string
    {
        return $this->currentPlace;
    }

    /**
     * @throws WorkflowException unless the game is in the place, or one of the places
     */
    public function assertPlace(string ...$places): void
    {
        if (! in_array($this->currentPlace, $places, true)) {
            throw new WorkflowException(implode(' or ', $places), $this->currentPlace ?? '<undefined>');
        }
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

    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * A message has been handled on the game.
     *
     * @return int the new version
     */
    public function incrementVersion(): int
    {
        return ++$this->version;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
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
     * A new random id (UUID v4) drawn from the random generator of the game, salted by the id of the game.
     *
     * The salt keeps two games with the same seed (a game and its replay) from creating the same ids, while they
     * consume their generators the same way: they draw the same values otherwise.
     */
    public function generateId(): string
    {
        $bytes = $this->getRandomizer()->getBytes(16) ^ self::idSalt($this->id);
        $bytes[6] = chr(ord($bytes[6]) & 0x0F | 0x40);
        $bytes[8] = chr(ord($bytes[8]) & 0x3F | 0x80);

        return Uuid::fromBinary($bytes)->toRfc4122();
    }

    /**
     * The 16 bytes salting the ids a game generates (generateId()).
     */
    public static function idSalt(string $gameId): string
    {
        return substr(hash('sha256', $gameId, true), 0, 16);
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
