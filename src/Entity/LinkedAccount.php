<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\OAuthProvider;
use App\Repository\LinkedAccountRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * An identity at a provider (OAuth2) linked to an account, which then signs in with it. Only the identifier of the
 * identity at the provider is kept (`subject`: the `sub` of Google, the id of a Discord user), no personal
 * data. An identity is linked to one account at most, an account to one identity of each provider at most.
 */
#[ORM\Entity(repositoryClass: LinkedAccountRepository::class)]
#[ORM\Table('linked_accounts')]
#[ORM\UniqueConstraint(name: 'linked_account_identity', columns: ['provider', 'subject'])]
#[ORM\UniqueConstraint(name: 'linked_account_user_provider', columns: ['user_id', 'provider'])]
class LinkedAccount
{
    #[ORM\Id]
    #[ORM\GeneratedValue('SEQUENCE')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 16, enumType: OAuthProvider::class)]
    private OAuthProvider $provider;

    #[ORM\Column(length: 255)]
    private string $subject;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $linkedAt;

    public function __construct(User $user, OAuthProvider $provider, string $subject, \DateTimeImmutable $linkedAt)
    {
        if (! $user->canLinkAccounts()) {
            throw new \InvalidArgumentException("The user {$user->getId()} cannot link accounts.");
        }

        $this->user = $user;
        $this->provider = $provider;
        $this->subject = $subject;
        $this->linkedAt = $linkedAt;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getProvider(): OAuthProvider
    {
        return $this->provider;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getLinkedAt(): \DateTimeImmutable
    {
        return $this->linkedAt;
    }
}
