<?php

declare(strict_types=1);

namespace App\Tests\Service\Tournament;

use App\DataFixtures\AppFixtures;
use App\Entity\Candidate;
use App\Entity\Deck;
use App\Entity\Game;
use App\Entity\Player;
use App\Entity\Tournament;
use App\Entity\TournamentEntry;
use App\Entity\TournamentMatch;
use App\Entity\User;
use App\Enum\MatchOutcome;
use App\Enum\TournamentStatus;
use App\Enum\UserType;
use App\Enum\VictoryCondition;
use App\Exception\Api\InvalidTournamentMatchException;
use App\Message\ConcedeGame;
use App\Repository\TournamentMatchRepository;
use App\Security\AccountDeleter;
use App\Service\MatchMaking\MatchMakingQueue;
use App\Service\Tournament\TournamentRunner;
use App\Tests\AbstractKernelTestCase;
use App\View\TournamentViewFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Running a tournament: locking it at H-1, beginning it at H, the forfeits after 10 minutes, the games of its matches,
 * the next rounds and its end.
 */
class TournamentRunnerTest extends AbstractKernelTestCase
{
    use ClockSensitiveTrait;

    /** H-1 */
    private const string LOCKS_AT = '2026-11-20 19:00:00';

    private const string STARTS_AT = '2026-11-20 20:00:00';

    private MockClock $clock;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $clock = self::mockTime('2026-11-20 12:00:00');
        self::assertInstanceOf(MockClock::class, $clock);
        $this->clock = $clock;
        $this->entityManager = self::service(EntityManagerInterface::class);
    }

    /**
     * @param non-empty-string $id
     */
    private function user(string $id): User
    {
        if ($id === 'john' || $id === 'jane') {
            return $this->entityManager->find(User::class, $id) ?? throw new \LogicException();
        }

        $user = $this->entityManager->find(User::class, $id);
        if (! $user instanceof User) {
            $user = new User($id, ucfirst($id), UserType::Human);
            $user->register($id, 'hash');
            $this->entityManager->persist($user);
        }

        return $user;
    }

    /**
     * A tournament beginning at STARTS_AT, organized by john, with these players registered.
     *
     * @param non-empty-string ...$players
     */
    private function tournament(string ...$players): Tournament
    {
        $tournament = new Tournament('tournament', $this->user('john'), new \DateTimeImmutable(self::STARTS_AT), str_repeat('k', 32), $this->clock->now());
        $this->entityManager->persist($tournament);
        $deck = $this->entityManager->find(Deck::class, AppFixtures::LION_STARTER_DECK) ?? throw new \LogicException();
        foreach ($players as $player) {
            $this->entityManager->persist(new TournamentEntry($tournament, $this->user($player), $deck, $this->clock->now()));
        }
        $this->entityManager->flush();

        return $tournament;
    }

    private function runAt(string $time): void
    {
        $this->clock->modify($time);
        self::service(TournamentRunner::class)->runDue();
    }

    /**
     * @return list<TournamentMatch>
     */
    private function roundMatches(Tournament $tournament, int $round): array
    {
        return self::service(TournamentMatchRepository::class)->findByRound($tournament, $round);
    }

    /**
     * The match of the round between two players (not a bye).
     */
    private function playedMatch(Tournament $tournament, int $round): TournamentMatch
    {
        return array_find($this->roundMatches($tournament, $round), static fn (TournamentMatch $match): bool => ! $match->isBye()) ?? throw new \LogicException('No match without a bye.');
    }

    public function testATournamentOfFewerThanTwoPlayersIsCancelledWhenItLocks(): void
    {
        $tournament = $this->tournament('jane');

        $this->runAt('2026-11-20 18:59:00');
        self::assertSame(TournamentStatus::Open, $tournament->getStatus());

        $this->runAt(self::LOCKS_AT);
        self::assertSame(TournamentStatus::Cancelled, $tournament->getStatus());
    }

    public function testLockingDrawsTheFirstRoundAndKeepsItSecret(): void
    {
        $tournament = $this->tournament('john', 'jane', 'kaede');

        $this->runAt(self::LOCKS_AT);

        self::assertSame(TournamentStatus::Locked, $tournament->getStatus());
        self::assertSame(4, $tournament->getSize());
        self::assertSame(0, $tournament->getCurrentRound());
        $matches = $this->roundMatches($tournament, 1);
        self::assertCount(2, $matches);
        self::assertCount(1, array_filter($matches, static fn (TournamentMatch $match): bool => $match->isBye()));
        $view = self::service(TournamentViewFactory::class)->create($tournament);
        self::assertSame([4, 2, 0, []], [$view->size, $view->roundCount, $view->currentRound, $view->rounds]);
    }

    public function testTheFirstRoundBeginsAtTheStartItsByesWon(): void
    {
        $tournament = $this->tournament('john', 'jane', 'kaede');
        $this->runAt(self::LOCKS_AT);

        $this->runAt('2026-11-20 19:59:00');
        self::assertSame(TournamentStatus::Locked, $tournament->getStatus());

        $this->runAt(self::STARTS_AT);

        self::assertSame(TournamentStatus::InProgress, $tournament->getStatus());
        self::assertSame(1, $tournament->getCurrentRound());
        self::assertEquals(new \DateTimeImmutable('2026-11-20 20:10:00'), $tournament->getJoinDeadline());
        $bye = array_find($this->roundMatches($tournament, 1), static fn (TournamentMatch $match): bool => $match->isBye());
        self::assertSame(MatchOutcome::Bye, $bye?->getOutcome());
        self::assertSame($bye->getFirst(), $bye->getWinner());
        self::assertFalse($this->playedMatch($tournament, 1)->hasResult());
        $view = self::service(TournamentViewFactory::class)->create($tournament);
        self::assertCount(1, $view->rounds);
        self::assertCount(2, $view->rounds[0]->matches);
    }

    public function testThePlayerWaitingWinsByForfeitOnceTenMinutesHavePassed(): void
    {
        $tournament = $this->tournament('john', 'jane');
        $this->runAt(self::LOCKS_AT);
        $this->runAt(self::STARTS_AT);
        $match = $this->roundMatches($tournament, 1)[0];

        $this->clock->modify('2026-11-20 20:05:00');
        self::service(MatchMakingQueue::class)->joinMatch($this->user('jane'), (int) $match->getId());
        self::assertSame(['jane'], self::service(TournamentViewFactory::class)->create($tournament)->rounds[0]->matches[0]->waiting);

        $this->runAt('2026-11-20 20:09:00');
        self::assertFalse($match->hasResult());

        $this->runAt('2026-11-20 20:10:00');

        self::assertSame(MatchOutcome::Forfeit, $match->getOutcome());
        self::assertSame('jane', $match->getWinner()?->getUser()->getId());
        self::assertTrue($match->getOpponentOf($match->getWinner())?->isEliminated());
        self::assertSame(0, $this->entityManager->getRepository(Candidate::class)->count([]));
        self::assertSame(TournamentStatus::Ended, $tournament->getStatus());
        self::assertSame('jane', $tournament->getWinner()?->getUser()->getId());
    }

    public function testNeitherPlayerJoiningEliminatesBoth(): void
    {
        $tournament = $this->tournament('john', 'jane');
        $this->runAt(self::LOCKS_AT);
        $this->runAt(self::STARTS_AT);

        $this->runAt('2026-11-20 20:10:00');

        $match = $this->roundMatches($tournament, 1)[0];
        self::assertSame(MatchOutcome::NoResult, $match->getOutcome());
        self::assertTrue($match->getFirst()->isEliminated());
        self::assertTrue($match->getSecond()?->isEliminated());
        self::assertSame(TournamentStatus::Ended, $tournament->getStatus());
        self::assertNull($tournament->getWinner());
    }

    public function testTheGameOfAMatchDecidesItAndTheNextRoundBegins(): void
    {
        $tournament = $this->tournament('john', 'jane', 'kaede');
        $this->runAt(self::LOCKS_AT);
        $this->runAt(self::STARTS_AT);
        $match = $this->playedMatch($tournament, 1);
        [$first, $second] = $match->getEntries();

        $queue = self::service(MatchMakingQueue::class);
        $queue->joinMatch($first->getUser(), (int) $match->getId());
        self::assertNotInstanceOf(Game::class, $match->getGame());
        $queue->joinMatch($second->getUser(), (int) $match->getId());

        // read again: the first assertion has told the analysis that the match has no game
        $game = self::service(TournamentMatchRepository::class)->find($match->getId())?->getGame();
        self::assertInstanceOf(Game::class, $game);
        self::assertTrue($game->isStandard());
        self::assertSame(0, $this->entityManager->getRepository(Candidate::class)->count([]));

        $this->clock->modify('2026-11-20 20:40:00');
        $loser = $game->getPlayers()->findFirst(static fn (int $key, Player $player): bool => $player->getUserId() === $first->getUser()->getId());
        self::assertInstanceOf(Player::class, $loser);
        self::service(MessageBusInterface::class)->dispatch(new ConcedeGame($game->getId(), $loser->getId()));

        self::assertSame([MatchOutcome::Game, VictoryCondition::Concession, $second], [$match->getOutcome(), $match->getVictoryCondition(), $match->getWinner()]);
        self::assertTrue($first->isEliminated());
        self::assertSame(2, $tournament->getCurrentRound());
        self::assertEquals(new \DateTimeImmutable('2026-11-20 20:40:00'), $tournament->getRoundStartedAt());
        $final = $this->roundMatches($tournament, 2);
        self::assertCount(1, $final);
        self::assertContains($second, $final[0]->getEntries());
        self::assertFalse($final[0]->isBye());
    }

    public function testJoiningAMatchReplacesAnotherWaitAndIsRefusedOnceTheTimeIsOver(): void
    {
        $tournament = $this->tournament('john', 'jane');
        $this->runAt(self::LOCKS_AT);
        $this->runAt(self::STARTS_AT);
        $match = $this->roundMatches($tournament, 1)[0];
        $queue = self::service(MatchMakingQueue::class);
        $queue->join($this->user('john'), AppFixtures::CRAB_STARTER_DECK);

        $queue->joinMatch($this->user('john'), (int) $match->getId());

        self::assertSame((int) $match->getId(), $queue->findMatchId($this->user('john')));
        self::assertSame(AppFixtures::LION_STARTER_DECK, $queue->findDeckId($this->user('john')));

        $this->clock->modify('2026-11-20 20:10:00');
        $this->expectException(InvalidTournamentMatchException::class);
        $queue->joinMatch($this->user('jane'), (int) $match->getId());
    }

    public function testAPlayerOfAnotherMatchCannotJoinIt(): void
    {
        $tournament = $this->tournament('john', 'jane');
        $this->runAt(self::LOCKS_AT);
        $this->runAt(self::STARTS_AT);
        $match = $this->roundMatches($tournament, 1)[0];

        $this->expectException(InvalidTournamentMatchException::class);
        self::service(MatchMakingQueue::class)->joinMatch($this->user('kaede'), (int) $match->getId());
    }

    public function testADeletedAccountForfeitsItsMatch(): void
    {
        $tournament = $this->tournament('john', 'jane');
        $this->runAt(self::LOCKS_AT);
        $this->runAt(self::STARTS_AT);

        self::service(AccountDeleter::class)->delete($this->user('john'));

        $match = $this->roundMatches($tournament, 1)[0];
        self::assertSame(MatchOutcome::Forfeit, $match->getOutcome());
        self::assertSame('jane', $match->getWinner()?->getUser()->getId());
        self::assertSame(TournamentStatus::Ended, $tournament->getStatus());
    }

    public function testADeletedAccountForfeitsTheRoundsToCome(): void
    {
        $tournament = $this->tournament('john', 'jane');
        $this->runAt(self::LOCKS_AT);
        self::service(AccountDeleter::class)->delete($this->user('jane'));

        $this->runAt(self::STARTS_AT);

        $match = $this->roundMatches($tournament, 1)[0];
        self::assertSame([MatchOutcome::Forfeit, 'john'], [$match->getOutcome(), $match->getWinner()?->getUser()->getId()]);
        self::assertSame('john', $tournament->getWinner()?->getUser()->getId());
    }
}
