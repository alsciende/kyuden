<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\CardDatabase\CardDatabaseImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:cards:import',
    description: 'Create or update the database of the cards (packs, cards, printings, external cards, starter decks) from fixtures/',
)]
readonly class CardsImportCommand
{
    public function __construct(
        private CardDatabaseImporter $cardDatabaseImporter,
    ) {
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $created = $this->cardDatabaseImporter->import();
        $io->success(sprintf(
            'Card database imported. Created: %s.',
            implode(', ', array_map(static fn (string $kind, int $count): string => "{$count} {$kind}", array_keys($created), $created)),
        ));

        return Command::SUCCESS;
    }
}
