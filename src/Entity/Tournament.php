<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TournamentFormat;
use App\Enum\TournamentStatus;
use App\Repository\TournamentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;

/**
 * A tournament an account organizes: nothing but its start (UTC), no name nor description (nothing to moderate). The
 * players register with a deck (TournamentEntry) until it locks, one hour before it begins; its organizer cancels it
 * until then. Its random draws (the byes, the pairings) come from its own seed, stored like that of a game. Once locked,
 * it has the size of its bracket and its first round drawn (TournamentMatch), kept secret until it begins: its current
 * round is 0 until then, and the pairings of a round are revealed when it begins. It ends with its winner, none when
 * no player is left.
 */
#[ORM\Entity(repositoryClass: TournamentRepository::class)]
#[ORM\Table('tournaments')]
#[ORM\Index(columns: ['starts_at'])]
class Tournament
{
    /**
     * The most players a tournament takes: a bracket of 64 places.
     */
    public const int MAX_ENTRANTS = 64;

    /**
     * How long before its start a tournament locks: no registration, change of deck or cancellation after that.
     */
    public const string LOCK_LEAD = '1 hour';

    /**
     * How long ahead of its creation a tournament begins at the least.
     */
    public const string MINIMUM_NOTICE = '12 hours';

    /**
     * How far apart two tournaments begin at the least, on the whole site, so that they never overlap.
     */
    public const string MINIMUM_SPACING = '12 hours';

    /**
     * A tournament begins on the hour or the half hour.
     */
    public const int START_STEP_MINUTES = 30;

    /**
     * How long the players of a match have to join the queue once its round has begun, else they forfeit.
     */
    public const string JOIN_DELAY = '10 minutes';

    #[ORM\Id]
    #[ORM\Column]
    private string $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private User $organizer;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $startsAt;

    #[ORM\Column(length: 32, enumType: TournamentFormat::class)]
    private TournamentFormat $format;

    #[ORM\Column(length: 16, enumType: TournamentStatus::class)]
    private TournamentStatus $status = TournamentStatus::Open;

    /**
     * 32 random bytes, in hexadecimal: the seed of the generator of its draws.
     */
    #[ORM\Column(length: 64)]
    private string $seed;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /**
     * The number of the places of its bracket (a power of 2), once locked.
     */
    #[ORM\Column(nullable: true)]
    private ?int $size = null;

    /**
     * The round being played, from 1; 0 until it begins.
     */
    #[ORM\Column]
    private int $currentRound = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $roundStartedAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?TournamentEntry $winner = null;

    /**
     * @param string $seed 32 random bytes
     */
    public function __construct(string $id, User $organizer, \DateTimeImmutable $startsAt, string $seed, \DateTimeImmutable $now, TournamentFormat $format = TournamentFormat::SingleElimination)
    {
        if (! $organizer->canTakePartInTournaments()) {
            throw new \InvalidArgumentException('Only an account organizes a tournament.');
        }

        $this->id = $id;
        $this->organizer = $organizer;
        $this->startsAt = $startsAt;
        $this->seed = bin2hex($seed);
        $this->createdAt = $now;
        $this->format = $format;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOrganizer(): User
    {
        return $this->organizer;
    }

    public function getStartsAt(): \DateTimeImmutable
    {
        return $this->startsAt;
    }

    /**
     * When it locks: one hour before it begins.
     */
    public function getLocksAt(): \DateTimeImmutable
    {
        return $this->startsAt->modify('-' . self::LOCK_LEAD);
    }

    public function getFormat(): TournamentFormat
    {
        return $this->format;
    }

    public function getStatus(): TournamentStatus
    {
        return $this->status;
    }

    public function getSeed(): string
    {
        return (string) hex2bin($this->seed);
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isCancelled(): bool
    {
        return $this->status === TournamentStatus::Cancelled;
    }

    /**
     * Whether the players still register, change their deck or unregister, and its organizer cancels it: open, and
     * not locked yet by the time.
     */
    public function isOpenAt(\DateTimeImmutable $now): bool
    {
        return $this->status === TournamentStatus::Open && $now < $this->getLocksAt();
    }

    public function cancel(): void
    {
        $this->status = TournamentStatus::Cancelled;
    }

    /**
     * The generator of its draws, from its seed: the same draws each time.
     */
    public function createRandomizer(): Randomizer
    {
        return new Randomizer(new Xoshiro256StarStar($this->getSeed()));
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    /**
     * Locked, with the size of its bracket: its first round is drawn, nothing changes any more for its players.
     */
    public function lock(int $size): void
    {
        if ($this->status !== TournamentStatus::Open) {
            throw new \LogicException("The tournament {$this->id} is not open.");
        }

        $this->status = TournamentStatus::Locked;
        $this->size = $size;
    }

    public function getCurrentRound(): int
    {
        return $this->currentRound;
    }

    public function getRoundStartedAt(): ?\DateTimeImmutable
    {
        return $this->roundStartedAt;
    }

    /**
     * Until when the players of the current round join the queue, null before it begins.
     */
    public function getJoinDeadline(): ?\DateTimeImmutable
    {
        return $this->roundStartedAt?->modify('+' . self::JOIN_DELAY);
    }

    /**
     * The next round begins, its pairings revealed: the first one once locked, then each one after the previous.
     */
    public function beginRound(int $round, \DateTimeImmutable $now): void
    {
        if (! in_array($this->status, [TournamentStatus::Locked, TournamentStatus::InProgress], true) || $round !== $this->currentRound + 1) {
            throw new \LogicException("The tournament {$this->id} cannot begin its round {$round}.");
        }

        $this->status = TournamentStatus::InProgress;
        $this->currentRound = $round;
        $this->roundStartedAt = $now;
    }

    public function getWinner(): ?TournamentEntry
    {
        return $this->winner;
    }

    /**
     * Ended, with its winner (none when no player is left).
     */
    public function end(?TournamentEntry $winner): void
    {
        if ($this->status !== TournamentStatus::InProgress) {
            throw new \LogicException("The tournament {$this->id} is not in progress.");
        }

        $this->status = TournamentStatus::Ended;
        $this->winner = $winner;
    }
}
