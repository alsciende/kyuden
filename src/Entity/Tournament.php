<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\TournamentFormat;
use App\Enum\TournamentStatus;
use App\Repository\TournamentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * A tournament an account organizes: nothing but its start (UTC), no name nor description (nothing to moderate). The
 * players register with a deck (TournamentEntry) until it locks, one hour before it begins; its organizer cancels it
 * until then. Its random draws (the byes, the pairings) come from its own seed, stored like that of a game.
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
}
