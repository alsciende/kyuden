<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use App\Enum\UserType;
use App\Exception\Api\UsernameTakenException;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Creates the accounts the humans sign in to: a guest becomes an account (same user: their games, decks and tokens
 * stay theirs), anyone else gets a new user. The caller flushes; the unique username is checked here, and by the
 * database when two humans take the same one at once.
 */
readonly class AccountRegistrar
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param User|null   $guest the guest becoming the account, null for a new user
     * @param string|null $name  the name at court of a new user (a guest keeps theirs)
     *
     * @throws UsernameTakenException
     */
    public function register(?User $guest, string $username, #[\SensitiveParameter] string $password, ?string $name = null): User
    {
        if ($this->userRepository->findOneByUsername($username) instanceof User) {
            throw new UsernameTakenException($username);
        }

        $user = $guest;
        if ($user === null) {
            if ($name === null) {
                throw new \InvalidArgumentException('A new user needs a name.');
            }
            $user = new User(Uuid::v4()->toString(), $name, UserType::Human);
            $this->entityManager->persist($user);
        }
        $user->register($username, $this->passwordHasher->hashPassword($user, $password));

        return $user;
    }
}
