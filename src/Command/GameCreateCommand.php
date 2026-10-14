<?php

declare(strict_types=1);

namespace App\Command;

use App\Message\CreateGame;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsCommand(
    name: 'app:game:create',
    description: 'Create a game',
)]
readonly class GameCreateCommand
{
    public function __construct(
        private MessageBusInterface $bus,
    ) {
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $gameId = Uuid::v4()->toString();
        $this->bus->dispatch(CreateGame::withRandomSeed($gameId));
        $io->success("Game {$gameId} created successfully.");

        return Command::SUCCESS;
    }
}
