<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\InvitationKind;
use App\Enum\UserType;
use App\Repository\InvitationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * An invitation to a friendly game: the host and the guest of the invitation enter the queue with it, and are matched
 * together only (App\Service\MatchMaking\FifoMatchMakingService), whoever comes first. It is open (the first who uses
 * it takes the seat), used once (`gameId`, once matched), and expires when nobody came. An invitation for the bot of
 * the host is used with its access token (App\Entity\AccessToken::$invitation), an invitation for a friend with its
 * code. Cancelling it deletes it.
 */
#[ORM\Entity(repositoryClass: InvitationRepository::class)]
#[ORM\Table('invitations')]
class Invitation
{
    /**
     * How long an invitation waits for its guest.
     */
    public const string LIFETIME = '+24 hours';

    #[ORM\Id]
    #[ORM\Column]
    private string $id;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $host;

    #[ORM\Column(length: 16, enumType: InvitationKind::class)]
    private InvitationKind $kind;

    /**
     * The bot of the host, for an invitation of its bot.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?User $bot;

    /**
     * The code a friend uses (InvitationCode), for an invitation of a friend.
     */
    #[ORM\Column(length: 16, unique: true, nullable: true)]
    private ?string $code;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $expiresAt;

    /**
     * The game the invitation led to, once its host and its guest have been matched.
     */
    #[ORM\Column(nullable: true)]
    private ?string $gameId = null;

    private function __construct(string $id, User $host, InvitationKind $kind, ?User $bot, ?string $code, \DateTimeImmutable $now)
    {
        if (! $host->canInvite()) {
            throw new \InvalidArgumentException('Only an account invites.');
        }

        $this->id = $id;
        $this->host = $host;
        $this->kind = $kind;
        $this->bot = $bot;
        $this->code = $code;
        $this->expiresAt = $now->modify(self::LIFETIME);
    }

    public static function forBot(string $id, User $host, User $bot, \DateTimeImmutable $now): self
    {
        if ($bot->getOwner() !== $host) {
            throw new \InvalidArgumentException('The bot of an invitation is a bot of its host.');
        }

        return new self($id, $host, InvitationKind::Bot, $bot, null, $now);
    }

    public static function forFriend(string $id, User $host, string $code, \DateTimeImmutable $now): self
    {
        return new self($id, $host, InvitationKind::Friend, null, $code, $now);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getHost(): User
    {
        return $this->host;
    }

    public function getKind(): InvitationKind
    {
        return $this->kind;
    }

    public function getBot(): ?User
    {
        return $this->bot;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getGameId(): ?string
    {
        return $this->gameId;
    }

    public function isUsed(): bool
    {
        return $this->gameId !== null;
    }

    public function isExpired(\DateTimeImmutable $now): bool
    {
        return ! $this->isUsed() && $now >= $this->expiresAt;
    }

    /**
     * Whether its guest can still enter the queue with it.
     */
    public function isPending(\DateTimeImmutable $now): bool
    {
        return ! $this->isUsed() && ! $this->isExpired($now);
    }

    /**
     * Whether a user can use it as its guest: the bot of the invitation, or anyone but the host for a friend.
     */
    public function accepts(User $user): bool
    {
        return match ($this->kind) {
            InvitationKind::Bot => $user === $this->bot,
            InvitationKind::Friend => $user !== $this->host && $user->getType() !== UserType::Bot,
        };
    }

    /**
     * Its host and its guest have been matched in a game.
     */
    public function use(string $gameId): void
    {
        $this->gameId = $gameId;
    }
}
