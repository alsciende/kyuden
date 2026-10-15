<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\AccessTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:token:revoke',
    description: 'Revoke every access token of a user',
)]
readonly class TokenRevokeCommand
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AccessTokenRepository $accessTokenRepository,
    ) {
    }

    public function __invoke(
        #[Argument('User ID')]
        string $userId,
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $user = $this->entityManager->find(User::class, $userId);
        if (! $user instanceof User) {
            throw new \RuntimeException("User {$userId} not found.");
        }

        $count = $this->accessTokenRepository->deleteByUser($user);
        $io->success("{$count} access token(s) of {$userId} revoked.");

        return Command::SUCCESS;
    }
}
