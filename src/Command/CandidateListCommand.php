<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Candidate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:candidate:list',
    description: 'Add a short description for your command',
)]
readonly class CandidateListCommand
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var Candidate[] $rows */
        $rows = $this->entityManager->getRepository(Candidate::class)->findAll();
        $io->table(
            ['Id', 'User', 'Deck'],
            array_map(
                fn (Candidate $candidate): array => [$candidate->getId(), $candidate->getUserId(), $candidate->getDeck()->getId()],
                $rows
            )
        );

        return Command::SUCCESS;
    }
}
