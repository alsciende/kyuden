<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * An account of the platform, authenticated by its access tokens (AccessToken). A bot is an account used by a program
 * (an AI agent), owned by the person responsible for it: only a bot gets API keys, so that a program playing through
 * one is known as a bot.
 *
 * Its id is its user identifier. A Player and a Candidate belong to a user.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table('users')]
class User implements UserInterface
{
    #[ORM\Id]
    #[ORM\Column]
    private string $id;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(enumType: UserType::class)]
    private UserType $type;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?User $owner;

    /**
     * @param non-empty-string $id
     */
    public function __construct(string $id, string $name, UserType $type, ?User $owner = null)
    {
        if (($type === UserType::Bot) !== ($owner instanceof User)) {
            throw new \InvalidArgumentException('A bot has an owner, a human has none.');
        }
        if ($owner instanceof User && $owner->getType() !== UserType::Human) {
            throw new \InvalidArgumentException('The owner of a bot is a human.');
        }

        $this->id = $id;
        $this->name = $name;
        $this->type = $type;
        $this->owner = $owner;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): UserType
    {
        return $this->type;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    #[\Override]
    public function getRoles(): array
    {
        return $this->type === UserType::Bot ? ['ROLE_USER', 'ROLE_BOT'] : ['ROLE_USER'];
    }

    #[\Override]
    public function eraseCredentials(): void
    {
    }

    /**
     * @return non-empty-string
     */
    #[\Override]
    public function getUserIdentifier(): string
    {
        \assert($this->id !== '');

        return $this->id;
    }
}
