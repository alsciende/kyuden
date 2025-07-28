<?php

declare(strict_types=1);

namespace App\Command;

use App\DataFixtures\StarterDeckLionTrait;
use App\Entity\Deck;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:deck:create',
    description: 'Add a short description for your command',
)]
readonly class DeckCreateCommand
{
    use StarterDeckLionTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $deck = new Deck(Uuid::v4()->toString());
        $deck->setName('Lion Clan Starter Deck');
        $deck->setContent($this->getLionStarterCards());
        $this->entityManager->persist($deck);
        $this->entityManager->flush();
        $io->success("Deck {$deck->getId()} created");

        return Command::SUCCESS;
    }
}
