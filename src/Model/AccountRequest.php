<?php

declare(strict_types=1);

namespace App\Model;

use App\Entity\User;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The account a human asks for (`POST /api/accounts`), checked by the Validator. A new user (group `new_user`) needs a
 * name at court; a guest keeps theirs.
 */
final readonly class AccountRequest
{
    public function __construct(
        #[Assert\Regex(User::USERNAME_PATTERN, message: 'The username has 3 to 32 letters, digits or _.')]
        public string $username,
        #[Assert\Length(min: User::MIN_PASSWORD_LENGTH, max: User::MAX_PASSWORD_LENGTH)]
        #[\SensitiveParameter]
        public string $password,
        #[Assert\NotNull(groups: ['new_user'])]
        #[Assert\NotBlank(allowNull: true)]
        #[Assert\Length(max: User::MAX_NAME_LENGTH)]
        public ?string $name = null,
    ) {
    }
}
