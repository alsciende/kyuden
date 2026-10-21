<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\User;
use App\Enum\Clan;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The account a human asks for (`POST /api/accounts`), checked by the Validator. A new user (group `new_user`) needs a
 * name at court; a guest keeps theirs. Both choose their preferred clan among the Great Clans.
 */
final readonly class AccountRequest
{
    public function __construct(
        #[Assert\Regex(User::USERNAME_PATTERN, message: 'The username has 3 to 32 letters, digits or _.')]
        public string $username,
        #[Assert\Length(min: User::MIN_PASSWORD_LENGTH, max: User::MAX_PASSWORD_LENGTH)]
        #[\SensitiveParameter]
        public string $password,
        #[Assert\Choice(callback: [self::class, 'getClanValues'], message: 'Choose one of the Great Clans: {{ choices }}.')]
        public string $clan,
        #[Assert\NotNull(groups: ['new_user'])]
        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Length(max: User::MAX_NAME_LENGTH)]
        public ?string $name = null,
    ) {
    }

    /**
     * @return list<string>
     */
    public static function getClanValues(): array
    {
        return array_map(static fn (Clan $clan): string => $clan->value, Clan::greatClans());
    }

    public function getClan(): Clan
    {
        return Clan::from($this->clan);
    }
}
