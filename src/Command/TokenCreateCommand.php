<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Enum\AccessTokenType;
use App\Security\AccessTokenIssuer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:token:create',
    description: 'Issue an access token to a user and show it, once',
)]
readonly class TokenCreateCommand
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AccessTokenIssuer $accessTokenIssuer,
    ) {
    }

    public function __invoke(
        #[Argument('User ID')]
        string $userId,
        InputInterface $input,
        OutputInterface $output,
        #[Option('Issue an API key (bots only) instead of a session')]
        bool $apiKey = false,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $user = $this->entityManager->find(User::class, $userId);
        if (! $user instanceof User) {
            throw new \RuntimeException("User {$userId} not found.");
        }

        $token = $this->accessTokenIssuer->issue($user, $apiKey ? AccessTokenType::ApiKey : AccessTokenType::Session);
        $this->entityManager->flush();
        $io->success("Access token of {$userId} (shown once, only its hash is stored):");
        $output->writeln($token);

        return Command::SUCCESS;
    }
}
