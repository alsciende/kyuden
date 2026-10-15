<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Candidate;
use App\Entity\Deck;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:candidate:create',
    description: 'Create a Candidate',
)]
readonly class CandidateCreateCommand
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(
        #[Argument('User ID')]
        string $userId,
        #[Argument('Deck ID')]
        string $deckId,
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $user = $this->entityManager->find(User::class, $userId);
        if (! $user instanceof User) {
            throw new \RuntimeException("User {$userId} not found.");
        }
        $deck = $this->entityManager->find(Deck::class, $deckId);
        if (! $deck instanceof Deck) {
            throw new \RuntimeException("Deck {$deckId} not found.");
        }
        $candidate = new Candidate();
        $candidate->setUser($user);
        $candidate->setDeck($deck);
        $this->entityManager->persist($candidate);
        $this->entityManager->flush();
        $io->success("Candidate {$candidate->getId()} created.");

        return Command::SUCCESS;
    }
}
