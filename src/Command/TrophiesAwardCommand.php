<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Game;
use App\Entity\Tournament;
use App\Repository\GameRepository;
use App\Repository\TournamentRepository;
use App\Service\Trophy\GameTrophies;
use App\Service\Trophy\TournamentTrophies;
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
 * ended, then those of the tournaments that have ended, as earned when their last match ended: for what was played
 * before a trophy existed (no trophy is earned both in a game and in a tournament, so that their order does not
 * matter). A trophy earned already is kept as it is, so that it runs again after each new trophy.
 */
#[AsCommand(
    name: 'app:trophies:award',
    description: 'Award the trophies of the games and the tournaments that have ended (those played before a trophy existed)',
)]
readonly class TrophiesAwardCommand
{
    public function __construct(
        private GameRepository $gameRepository,
        private GameTrophies $gameTrophies,
        private TournamentRepository $tournamentRepository,
        private TournamentTrophies $tournamentTrophies,
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
                ? $this->gameTrophies->trophiesEarnedIn($game)
                : $this->gameTrophies->award($game, $game->getUpdatedAt() ?? $this->clock->now());
            foreach ($earned as [$player, $trophy]) {
                $io->writeln(sprintf('%s: %s earns %s', $gameId, $player->getUserId(), $trophy->value));
                ++$awarded;
            }
            // flushed game by game, so that a trophy is earned in the first game that gives it
            $this->entityManager->flush();
            $this->entityManager->clear();
        }

        $tournaments = 0;
        foreach ($this->tournamentRepository->findEndedIds() as $tournamentId) {
            $tournament = $this->tournamentRepository->find($tournamentId);
            if (! $tournament instanceof Tournament) {
                continue;
            }
            ++$tournaments;
            $earned = $dryRun
                ? $this->tournamentTrophies->trophiesEarnedIn($tournament)
                : $this->tournamentTrophies->award($tournament, $this->tournamentTrophies->endedAt($tournament) ?? $this->clock->now());
            foreach ($earned as [$entry, $trophy]) {
                $io->writeln(sprintf('tournament %s: %s earns %s', $tournamentId, $entry->getUser()->getId(), $trophy->value));
                ++$awarded;
            }
            $this->entityManager->flush();
            $this->entityManager->clear();
        }

        $io->success($dryRun
            ? sprintf('%d trophies to award in %d games and %d tournaments (the trophies earned already included).', $awarded, $games, $tournaments)
            : sprintf('%d trophies awarded in %d games and %d tournaments.', $awarded, $games, $tournaments));

        return Command::SUCCESS;
    }
}
