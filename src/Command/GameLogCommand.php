<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Game;
use App\Entity\Player;
use App\GameLog\GameLogRenderer;
use App\Repository\GameRepository;
use App\View\GameLogViewFactory;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:game:log',
    description: 'Show the log of a game, as a player (or a spectator) reads it',
)]
readonly class GameLogCommand
{
    public function __construct(
        private GameRepository $repository,
        private GameLogViewFactory $gameLogViewFactory,
        private GameLogRenderer $renderer,
    ) {
    }

    public function __invoke(
        #[Argument('Game ID')]
        string $id,
        InputInterface $input,
        OutputInterface $output,
        #[Option('User ID of the player reading the log; without it, only what is public is shown')]
        ?string $as = null,
        #[Option('Only the messages handled after this version')]
        int $since = 0,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $game = $this->repository->find($id);
        if (! $game instanceof Game) {
            throw new \RuntimeException("Game {$id} not found.");
        }

        $reader = $as === null ? null : $this->findPlayer($game, $as);
        $log = $this->gameLogViewFactory->create($game, $reader, $since);
        foreach ($log->messages as $message) {
            $sender = $message->playerId === null ? '' : ' by ' . $game->getPlayer($message->playerId)->getUserId();
            $io->writeln(sprintf('<info>#%d</info> %s%s', $message->version, $message->type, $sender));
            foreach ($message->entries as $entry) {
                $io->writeln('    ' . $this->renderer->render($entry, $game));
            }
        }

        return Command::SUCCESS;
    }

    private function findPlayer(Game $game, string $userId): Player
    {
        foreach ($game->getPlayers() as $player) {
            if ($player->getUserId() === $userId) {
                return $player;
            }
        }

        throw new \RuntimeException("User {$userId} is not a player of game {$game->getId()}.");
    }
}
