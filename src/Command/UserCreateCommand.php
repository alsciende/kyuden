<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Enum\UserType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:create',
    description: 'Create a user, human or bot',
)]
readonly class UserCreateCommand
{
    public function __construct(
        private EntityManagerInterface $entityManager,
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
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $io->success("User {$id} created.");

        return Command::SUCCESS;
    }
}
