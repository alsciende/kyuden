<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\Bot\HouseBot;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:bot:play',
    description: 'Make the house bot play in a game until it has nothing to do',
)]
readonly class BotPlayCommand
{
    public function __construct(
        private HouseBot $houseBot,
    ) {
    }

    public function __invoke(
        #[Argument('Game ID')]
        string $gameId,
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $actions = $this->houseBot->play($gameId);
        $io->success("The house bot ({$this->houseBot->getUserId()}) sent {$actions} action(s).");

        return Command::SUCCESS;
    }
}
