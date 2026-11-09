<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Game;
use App\Entity\Player;
use App\Exception\Data\ReplayDivergedException;
use App\Exception\Rules\GameNotEndedException;
use App\GameLog\GameLogRenderer;
use App\Repository\GameRepository;
use App\Model\GameReplay;
use App\Service\Replay\GameReplayer;
use App\Model\ReplayStep;
use App\View\GameLogViewFactory;
use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function Symfony\Component\String\u;

/**
 * Replays a game that has ended as one of its players follows it, stopping before each of their actions, in a
 * transaction rolled back at the end: nothing of the replay stays.
 */
#[AsCommand(
    name: 'app:game:replay',
    description: 'Replay a game that has ended from its events, showing its log as one of its players reads it',
)]
readonly class GameReplayCommand
{
    public function __construct(
        private GameRepository $repository,
        private GameReplayer $replayer,
        private GameLogViewFactory $gameLogViewFactory,
        private GameLogRenderer $renderer,
        private Connection $connection,
    ) {
    }

    public function __invoke(
        #[Argument('Game ID')]
        string $id,
        InputInterface $input,
        OutputInterface $output,
        #[Option('User ID of the player following the replay (required)')]
        ?string $as = null,
        #[Option('Wait for Enter before each action of the player')]
        bool $step = false,
    ): int {
        $io = new SymfonyStyle($input, $output);
        if ($as === null) {
            throw new \RuntimeException('The player following the replay is required: --as=<user id>.');
        }
        $game = $this->repository->find($id);
        if (! $game instanceof Game) {
            throw new \RuntimeException("Game {$id} not found.");
        }

        $this->connection->beginTransaction();
        try {
            $replay = $this->replayer->start($game, $this->findPlayer($game, $as));
            do {
                $replayStep = $this->replayer->proceed($replay);
                $this->writeLog($io, $replay, $replayStep);
                // the replay stops before each action of the player
                if ($replayStep->next !== null && $replayStep->next->getVersion() === $replayStep->to + 1) {
                    $io->writeln(sprintf('<comment>-- next, you: #%d %s</comment>', $replayStep->next->getVersion(), u($replayStep->next->getName())->afterLast('\\')->snake()->toString()));
                    if ($step) {
                        $io->ask('Press Enter to proceed', '');
                    }
                }
            } while (! $replayStep->ended);

            $divergence = $this->replayer->findDivergence($replay);
            if ($divergence !== null) {
                $io->error("The replay differs from the game from version {$divergence}.");

                return Command::FAILURE;
            }
            $io->success("Replayed {$game->getVersion()} events: identical to the game.");

            return Command::SUCCESS;
        } catch (ReplayDivergedException|GameNotEndedException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        } finally {
            $this->connection->rollBack();
        }
    }

    /**
     * The log of the versions the step has replayed, as the viewer reads it.
     */
    private function writeLog(SymfonyStyle $io, GameReplay $replay, ReplayStep $replayStep): void
    {
        $game = $this->replayer->getReplayGame($replay);
        if (! $game instanceof Game) {
            return;
        }

        $viewer = $game->getPlayer($replay->getReplayViewerId());
        foreach ($this->gameLogViewFactory->create($game, $viewer, $replayStep->from)->messages as $message) {
            $sender = match ($message->playerId) {
                null => '',
                $viewer->getId() => ' by you',
                default => ' by ' . $game->getPlayer($message->playerId)->getUserId(),
            };
            $io->writeln(sprintf('<info>#%d</info> %s%s', $message->version, $message->type, $sender));
            foreach ($message->entries as $entry) {
                $io->writeln('    ' . $this->renderer->render($entry, $game));
            }
        }
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
