<?php

declare(strict_types=1);

namespace App\Service\Tournament;

use App\Entity\Candidate;
use App\Entity\Game;
use App\Entity\Tournament;
use App\Entity\TournamentEntry;
use App\Entity\TournamentMatch;
use App\Entity\User;
use App\Enum\MatchOutcome;
use App\Enum\TournamentStatus;
use App\Enum\VictoryCondition;
use App\Repository\CandidateRepository;
use App\Repository\TournamentEntryRepository;
use App\Repository\TournamentMatchRepository;
use App\Repository\TournamentRepository;
use App\Service\Tournament\Format\Pairing;
use App\Service\Tournament\Format\TournamentFormatInterface;
use App\Service\Tournament\Format\TournamentFormats;
use App\State\Manager\GameStateManager;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * Runs the tournaments, through their format (TournamentFormatInterface):
 *
 * - at H-1 (the task RunTournaments of the worker, every minute), locks a tournament: the size of its bracket and its
 *   first round drawn, kept secret; fewer than 2 players cancels it;
 * - at H, begins its first round: its pairings revealed, its byes won at once, as the matches of a player whose account
 *   was deleted (forfeit);
 * - once the players of a round have had 10 minutes to join the queue with their match (Tournament::JOIN_DELAY), the
 *   matches without a game end: the player waiting in the queue wins by forfeit, neither leaves no result (both
 *   eliminated);
 * - once a game of a match ends (TournamentGameEndedListener), records its result: its winner, or no result for a game
 *   without a winner;
 * - once every match of a round has a result, begins the next round or ends the tournament with its winner.
 *
 * The row of the tournament is locked meanwhile, so that two matches ending at once do not both begin the next round;
 * the queue is locked by the worker and by a deleted account, so that a candidate is not matched in a game while their
 * match ends.
 */
final readonly class TournamentRunner
{
    public function __construct(
        private TournamentRepository $tournamentRepository,
        private TournamentEntryRepository $tournamentEntryRepository,
        private TournamentMatchRepository $tournamentMatchRepository,
        private CandidateRepository $candidateRepository,
        private TournamentFormats $formats,
        private GameStateManager $gameStateManager,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Moves on every tournament whose time has come, each in a transaction of its own.
     */
    public function runDue(): void
    {
        foreach ($this->tournamentRepository->findIdsToRun($this->clock->now()) as $id) {
            try {
                $this->candidateRepository->withQueueLock(function () use ($id): void {
                    $this->entityManager->wrapInTransaction(function () use ($id): void {
                        $tournament = $this->tournamentRepository->findForUpdate($id);
                        if ($tournament instanceof Tournament) {
                            $this->run($tournament, $this->clock->now());
                        }
                    });
                });
            } catch (\Throwable $exception) {
                // the others still run
                $this->logger->error('The tournament {id} could not be run: {message}', ['id' => $id, 'message' => $exception->getMessage(), 'exception' => $exception]);
                $this->entityManager->clear();
            }
        }
    }

    /**
     * Records the result of the match of a game that has ended (nothing for a game of no tournament): its winner, or no
     * result without a winner. In the transaction of the message that ends the game.
     */
    public function recordGameResult(Game $game): void
    {
        $match = $this->tournamentMatchRepository->findOneByGame($game);
        if (! $match instanceof TournamentMatch) {
            return;
        }

        $tournament = $this->tournamentRepository->findForUpdate($match->getTournament()->getId()) ?? throw new \LogicException('The tournament of a match exists.');
        if ($match->hasResult() || $tournament->getStatus() !== TournamentStatus::InProgress) {
            return;
        }

        $now = $this->clock->now();
        $state = $this->gameStateManager->getState($game);
        $winnerPlayer = $state->winnerId === null ? null : $game->getPlayer($state->winnerId);
        $winner = $winnerPlayer === null ? null : array_find($match->getEntries(), static fn (TournamentEntry $entry): bool => $entry->getUser()->getId() === $winnerPlayer->getUserId());
        if ($winner instanceof TournamentEntry) {
            $this->win($match, $winner, MatchOutcome::Game, $now, $state->victoryCondition);
        } else {
            $this->endWithoutResult($match, $now);
        }

        $this->advance($tournament, $now);
    }

    /**
     * A deleted account forfeits the tournaments under way where it is still in play: the match of its current round
     * without a game (a game in progress is conceded, and its end records the result), the matches of the rounds to
     * come once they begin (its user is deleted).
     */
    public function withdraw(User $user): void
    {
        foreach ($this->tournamentEntryRepository->findInPlayOfUser($user) as $entry) {
            $this->candidateRepository->withQueueLock(function () use ($entry): void {
                $this->entityManager->wrapInTransaction(function () use ($entry): void {
                    $tournament = $this->tournamentRepository->findForUpdate($entry->getTournament()->getId());
                    if (! $tournament instanceof Tournament || $tournament->getStatus() !== TournamentStatus::InProgress) {
                        return;
                    }

                    $now = $this->clock->now();
                    foreach ($this->tournamentMatchRepository->findByRound($tournament, $tournament->getCurrentRound()) as $match) {
                        if ($match->hasResult() || $match->getGame() instanceof Game || ! in_array($entry, $match->getEntries(), true)) {
                            continue;
                        }

                        $opponent = $match->getOpponentOf($entry);
                        if ($opponent instanceof TournamentEntry) {
                            $this->win($match, $opponent, MatchOutcome::Forfeit, $now);
                        } else {
                            $this->endWithoutResult($match, $now);
                        }
                    }

                    $this->advance($tournament, $now);
                });
            });
        }
    }

    private function run(Tournament $tournament, \DateTimeImmutable $now): void
    {
        match ($tournament->getStatus()) {
            TournamentStatus::Open => $now >= $tournament->getLocksAt() ? $this->lock($tournament) : null,
            TournamentStatus::Locked => $now >= $tournament->getStartsAt() ? $this->beginRound($tournament, 1, $now) : null,
            TournamentStatus::InProgress => $this->forfeitAbsentPlayers($tournament, $now),
            default => null,
        };
    }

    private function lock(Tournament $tournament): void
    {
        $entries = $this->tournamentEntryRepository->findByTournament($tournament);
        if (count($entries) < 2) {
            $tournament->cancel();

            return;
        }

        $format = $this->formatOf($tournament);
        $tournament->lock($format->getSize(count($entries)));
        $this->persistRound($tournament, 1, $format->pairFirstRound($entries, $tournament->createRandomizer()));
    }

    private function beginRound(Tournament $tournament, int $round, \DateTimeImmutable $now): void
    {
        $tournament->beginRound($round, $now);
        foreach ($this->tournamentMatchRepository->findByRound($tournament, $round) as $match) {
            $this->endAtOnce($match, $now);
        }

        $this->advance($tournament, $now);
    }

    /**
     * A bye is won at once, as a match against a deleted account (by forfeit); between two deleted accounts, it has no
     * result.
     */
    private function endAtOnce(TournamentMatch $match, \DateTimeImmutable $now): void
    {
        $present = array_values(array_filter($match->getEntries(), static fn (TournamentEntry $entry): bool => ! $entry->getUser()->isDeleted()));
        if ($match->isBye()) {
            $present === [] ? $this->endWithoutResult($match, $now) : $this->win($match, $present[0], MatchOutcome::Bye, $now);
        } elseif (count($present) === 1) {
            $this->win($match, $present[0], MatchOutcome::Forfeit, $now);
        } elseif ($present === []) {
            $this->endWithoutResult($match, $now);
        }
    }

    private function forfeitAbsentPlayers(Tournament $tournament, \DateTimeImmutable $now): void
    {
        $deadline = $tournament->getJoinDeadline();
        if ($deadline === null || $now < $deadline) {
            return;
        }

        foreach ($this->tournamentMatchRepository->findByRound($tournament, $tournament->getCurrentRound()) as $match) {
            if ($match->hasResult() || $match->getGame() instanceof Game) {
                continue;
            }

            $waiting = array_map(static fn (Candidate $candidate): User => $candidate->getUser(), $this->candidateRepository->findBy(['match' => $match]));
            $present = array_values(array_filter($match->getEntries(), static fn (TournamentEntry $entry): bool => in_array($entry->getUser(), $waiting, true)));
            if (count($present) === 1) {
                $this->win($match, $present[0], MatchOutcome::Forfeit, $now);
            } else {
                $this->endWithoutResult($match, $now);
            }
        }

        $this->advance($tournament, $now);
    }

    /**
     * Once every match of the current round has a result, the next round begins, or the tournament ends.
     */
    private function advance(Tournament $tournament, \DateTimeImmutable $now): void
    {
        $this->entityManager->flush();
        $round = $tournament->getCurrentRound();
        $matches = $this->tournamentMatchRepository->findByRound($tournament, $round);
        if (array_any($matches, static fn (TournamentMatch $match): bool => ! $match->hasResult())) {
            return;
        }

        $format = $this->formatOf($tournament);
        $pairings = $format->pairNextRound($tournament, $matches);
        if ($pairings === null) {
            $tournament->end($format->findWinner($tournament, $matches));
            $this->entityManager->flush();

            return;
        }

        $this->persistRound($tournament, $round + 1, $pairings);
        $this->beginRound($tournament, $round + 1, $now);
    }

    /**
     * @param list<Pairing> $pairings
     */
    private function persistRound(Tournament $tournament, int $round, array $pairings): void
    {
        foreach ($pairings as $pairing) {
            $this->entityManager->persist(new TournamentMatch($tournament, $round, $pairing->table, $pairing->first, $pairing->second));
        }
        $this->entityManager->flush();
    }

    private function win(TournamentMatch $match, TournamentEntry $winner, MatchOutcome $outcome, \DateTimeImmutable $now, ?VictoryCondition $victoryCondition = null): void
    {
        $match->end($winner, $outcome, $now, $victoryCondition);
        $match->getOpponentOf($winner)?->eliminate();
        $this->leaveQueue($match);
    }

    private function endWithoutResult(TournamentMatch $match, \DateTimeImmutable $now): void
    {
        $match->end(null, MatchOutcome::NoResult, $now);
        foreach ($match->getEntries() as $entry) {
            $entry->eliminate();
        }
        $this->leaveQueue($match);
    }

    /**
     * The players still waiting with a match that has ended leave the queue.
     */
    private function leaveQueue(TournamentMatch $match): void
    {
        if ($match->getId() === null) {
            return;
        }

        foreach ($this->candidateRepository->findBy(['match' => $match]) as $candidate) {
            $this->entityManager->remove($candidate);
        }
    }

    private function formatOf(Tournament $tournament): TournamentFormatInterface
    {
        return $this->formats->get($tournament->getFormat());
    }
}
