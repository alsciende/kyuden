<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Game;
use App\Repository\GameRepository;
use App\Service\Trophy\GameTrophies;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Awards the trophies of the games that have ended, the first ended first, as if they had been earned when each game
 * ended: for the games played before a trophy existed. A trophy earned already is kept as it is, so that it runs again
 * after each new trophy.
 */
#[AsCommand(
    name: 'app:trophies:award',
    description: 'Award the trophies of the games that have ended (those played before a trophy existed)',
)]
readonly class TrophiesAwardCommand
{
    public function __construct(
        private GameRepository $gameRepository,
        private GameTrophies $gameTrophies,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(
        InputInterface $input,
        OutputInterface $output,
        #[Option('Show the trophies that would be awarded, without awarding them')]
        bool $dryRun = false,
    ): int {
        $io = new SymfonyStyle($input, $output);
        $awarded = 0;
        $games = 0;
        foreach ($this->gameRepository->findEndedIds() as $gameId) {
            $game = $this->gameRepository->find($gameId);
            if (! $game instanceof Game) {
                continue;
            }
            ++$games;
            $earned = $dryRun
                ? $this->gameTrophies->of($game)
                : $this->gameTrophies->award($game, $game->getUpdatedAt() ?? $this->clock->now());
            foreach ($earned as [$player, $trophy]) {
                $io->writeln(sprintf('%s: %s earns %s', $gameId, $player->getUserId(), $trophy->value));
                ++$awarded;
            }
            // flushed game by game, so that a trophy is earned in the first game that gives it
            $this->entityManager->flush();
            $this->entityManager->clear();
        }

        $io->success($dryRun
            ? sprintf('%d trophies to award in %d games (the trophies earned already included).', $awarded, $games)
            : sprintf('%d trophies awarded in %d games.', $awarded, $games));

        return Command::SUCCESS;
    }
}
