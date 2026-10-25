<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\Clan;
use App\Enum\UserType;
use App\Model\UserSettings;
use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * An account of the platform, authenticated by its access tokens (AccessToken). A bot is an account used by a program
 * (an AI agent), owned by the person responsible for it: only a bot gets API keys, so that a program playing through
 * one is known as a bot.
 *
 * Its id is its user identifier. A Player and a Candidate belong to a user. Its name (its name at court) is what the
 * other players see, not unique. A human signs in with a username and a password once registered; before that, it is
 * a guest. The username is unique whatever its case (`usernameCanonical`). Their preferred clan, one of the Great Clans,
 * is chosen when they create their account and changed at any time. Their settings (UserSettings) are their preferences,
 * e.g. how the web client draws the cards.
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table('users')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const int MAX_NAME_LENGTH = 64;

    public const string USERNAME_PATTERN = '/^[A-Za-z0-9_]{3,32}$/D';

    public const int MIN_PASSWORD_LENGTH = 8;

    /** the limit of the password hashers of Symfony */
    public const int MAX_PASSWORD_LENGTH = 4096;

    #[ORM\Id]
    #[ORM\Column]
    private string $id;

    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $username = null;

    /** the username in lower case, unique: no two usernames differ by their case only */
    #[ORM\Column(length: 32, unique: true, nullable: true)]
    private ?string $usernameCanonical = null;

    /** the hash of the password */
    #[ORM\Column(nullable: true)]
    private ?string $password = null;

    #[ORM\Column(enumType: UserType::class)]
    private UserType $type;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?User $owner;

    #[ORM\Column(length: 16, nullable: true, enumType: Clan::class)]
    private ?Clan $preferredClan = null;

    /**
     * @var array<mixed> the settings as stored, read through UserSettings
     */
    #[ORM\Column(type: Types::JSON)]
    private array $settings = [];

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

    public function rename(string $name): void
    {
        $this->name = $name;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public static function canonicalizeUsername(string $username): string
    {
        return strtolower($username);
    }

    /**
     * A human who has no username yet: they cannot sign in, only use the token they were given.
     */
    public function isGuest(): bool
    {
        return $this->type === UserType::Human && $this->username === null;
    }

    /**
     * Turns a guest into an account a human signs in to.
     */
    public function register(string $username, string $hashedPassword): void
    {
        if (! $this->isGuest()) {
            throw new \LogicException("The user {$this->id} is not a guest.");
        }

        $this->username = $username;
        $this->usernameCanonical = self::canonicalizeUsername($username);
        $this->password = $hashedPassword;
    }

    #[\Override]
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $hashedPassword): void
    {
        $this->password = $hashedPassword;
    }

    public function getType(): UserType
    {
        return $this->type;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function getPreferredClan(): ?Clan
    {
        return $this->preferredClan;
    }

    public function preferClan(Clan $clan): void
    {
        if (! in_array($clan, Clan::greatClans(), true)) {
            throw new \InvalidArgumentException("The clan {$clan->value} is not a Great Clan.");
        }

        $this->preferredClan = $clan;
    }

    public function getSettings(): UserSettings
    {
        return UserSettings::fromArray($this->settings);
    }

    public function changeSettings(UserSettings $settings): void
    {
        $this->settings = $settings->toArray();
    }

    #[\Override]
    public function getRoles(): array
    {
        return $this->type === UserType::Bot ? ['ROLE_USER', 'ROLE_BOT'] : ['ROLE_USER'];
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
