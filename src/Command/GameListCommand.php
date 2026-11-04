<?php

declare(strict_types=1);

namespace App\Command;

use App\View\GameInProgressViewFactory;
use App\View\Model\GameInProgressPlayerView;
use App\View\Model\GameInProgressView;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:game:list',
    description: 'List the games in progress (started, not ended), the last started first',
)]
readonly class GameListCommand
{
    public function __construct(
        private GameInProgressViewFactory $gameInProgressViewFactory,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $io->table(
            ['Id', 'Version', 'Place', 'Friendly', 'Players', 'Spectators'],
            array_map(
                fn (GameInProgressView $game): array => [
                    $game->id,
                    $game->version,
                    $game->place,
                    $game->friendly ? 'yes' : 'no',
                    implode(' vs ', array_map($this->describePlayer(...), $game->players)),
                    $game->spectators,
                ],
                $this->gameInProgressViewFactory->createAll(),
            ),
        );

        return Command::SUCCESS;
    }

    private function describePlayer(GameInProgressPlayerView $player): string
    {
        $details = array_filter([$player->clan?->value, $player->bot ? 'bot' : null]);

        return sprintf('%s [%s]%s', $player->name, $player->userId, $details === [] ? '' : ' (' . implode(', ', $details) . ')');
    }
}
