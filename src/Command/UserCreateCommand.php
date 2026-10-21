<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Enum\UserType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:user:create',
    description: 'Create a user, human or bot; a human with a username signs in with the password asked',
)]
readonly class UserCreateCommand
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator,
    ) {
    }

    public function __invoke(
        #[Argument('User ID')]
        string $id,
        #[Argument('Name')]
        string $name,
        InputInterface $input,
        OutputInterface $output,
        #[Option('The user is a bot, owned by a human (--owner)')]
        bool $bot = false,
        #[Option('User ID of the human who owns the bot')]
        ?string $owner = null,
        #[Option('Username of a human, who signs in with it and the password asked')]
        ?string $username = null,
    ): int {
        $io = new SymfonyStyle($input, $output);
        if ($id === '') {
            throw new \RuntimeException('The user ID cannot be empty.');
        }
        if ($this->entityManager->find(User::class, $id) instanceof User) {
            throw new \RuntimeException("User {$id} already exists.");
        }
        $ownerUser = null;
        if ($owner !== null) {
            $ownerUser = $this->entityManager->find(User::class, $owner);
            if (! $ownerUser instanceof User) {
                throw new \RuntimeException("User {$owner} not found.");
            }
        }

        $user = new User($id, $name, $bot ? UserType::Bot : UserType::Human, $ownerUser);
        if ($username !== null) {
            if ($bot) {
                throw new \RuntimeException('A bot has no username: it plays with an API key.');
            }
            $user->register($username, $this->passwordHasher->hashPassword($user, $this->askPassword($io, $username)));
        }
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $io->success("User {$id} created.");

        return Command::SUCCESS;
    }

    private function askPassword(SymfonyStyle $io, string $username): string
    {
        $errors = $this->validator->validate($username, new Assert\Regex(User::USERNAME_PATTERN, message: 'The username has 3 to 32 letters, digits or _.'));
        if ($errors->count() > 0) {
            throw new \RuntimeException((string) $errors->get(0)->getMessage());
        }
        if ($this->userRepository->findOneByUsername($username) instanceof User) {
            throw new \RuntimeException("The username {$username} is taken.");
        }

        $password = $io->askHidden('Password', function (mixed $password): string {
            $password = \is_string($password) ? $password : '';
            $errors = $this->validator->validate($password, new Assert\Length(min: User::MIN_PASSWORD_LENGTH, max: User::MAX_PASSWORD_LENGTH));
            if ($errors->count() > 0) {
                throw new \RuntimeException((string) $errors->get(0)->getMessage());
            }

            return $password;
        });
        \assert(\is_string($password));

        return $password;
    }
}
