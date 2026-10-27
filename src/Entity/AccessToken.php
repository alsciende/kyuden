<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AccessTokenType;
use App\Enum\UserType;
use App\Repository\AccessTokenRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * A token authenticating its user on the API (`Authorization: Bearer <token>`, App\Security\AccessTokenHandler).
 * Only its hash is stored: the token is a random secret of high entropy, so a fast hash (SHA-256) is enough and lets
 * the token be found by its hash. An API key is given to a bot only; the key of an invitation for a bot is limited to it
 * (App\Security\AccessTokenHandler): the bot joins the queue with it, and the key expires with it.
 */
#[ORM\Entity(repositoryClass: AccessTokenRepository::class)]
#[ORM\Table('access_tokens')]
class AccessToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue('SEQUENCE')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 64, unique: true)]
    private string $hash;

    #[ORM\Column(enumType: AccessTokenType::class)]
    private AccessTokenType $type;

    /**
     * The invitation an API key is limited to.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?Invitation $invitation;

    public function __construct(User $user, #[\SensitiveParameter] string $token, AccessTokenType $type, ?Invitation $invitation = null)
    {
        if ($type === AccessTokenType::ApiKey && $user->getType() !== UserType::Bot) {
            throw new \InvalidArgumentException('Only a bot gets API keys.');
        }
        if ($invitation instanceof Invitation && ($type !== AccessTokenType::ApiKey || $invitation->getBot() !== $user)) {
            throw new \InvalidArgumentException('Only the API key of the bot of an invitation is limited to it.');
        }

        $this->user = $user;
        $this->hash = self::hash($token);
        $this->type = $type;
        $this->invitation = $invitation;
    }

    public static function hash(#[\SensitiveParameter] string $token): string
    {
        return hash('sha256', $token);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getHash(): string
    {
        return $this->hash;
    }

    public function getType(): AccessTokenType
    {
        return $this->type;
    }

    public function getInvitation(): ?Invitation
    {
        return $this->invitation;
    }
}
