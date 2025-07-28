<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Game;
use App\Entity\Player;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:game:list',
    description: 'Add a short description for your command',
)]
readonly class GameListCommand
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        /** @var Game[] $rows */
        $rows = $this->entityManager->getRepository(Game::class)->findAll();
        $io->table(
            ['Id', 'Current Place', 'Players'],
            array_map(
                fn (Game $game): array => [
                    $game->getId(),
                    $game->getCurrentPlace(),
                    implode(' & ', array_map(fn (Player $player): string => $player->getUserId(), $game->getPlayers()->toArray())),
                ],
                $rows
            )
        );

        return Command::SUCCESS;
    }
}
