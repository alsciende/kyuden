<?php

declare(strict_types=1);

namespace App\Tests\Service\Trophy;

use App\DataFixtures\AppFixtures;
use App\Entity\Deck;
use App\Entity\Game;
use App\Entity\Tournament;
use App\Entity\TournamentEntry;
use App\Entity\TournamentMatch;
use App\Entity\User;
use App\Enum\Trophy;
use App\Enum\VictoryCondition;
use App\Service\Trophy\GameTrophies;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\GameState;
use App\State\Model\PlayerState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\GameBuilder;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;

class GameTrophiesTest extends AbstractKernelTestCase
{
    /**
     * A game that has ended, won by the first player of the scenario, each player with the stronghold given.
     *
     * @param array<string, string> $strongholds by player alias
     */
    private function ended(array $strongholds, VictoryCondition $condition, ?\Closure $configure = null): Scenario
    {
        $builder = $this->scenario()->atPlace(Game::STATE_ENDED);
        if ($configure instanceof \Closure) {
            $configure($builder);
        }
        foreach ($strongholds as $alias => $stronghold) {
            $builder->withPlayer($alias, static fn (PlayerBuilder $player): PlayerBuilder => $player->stronghold($stronghold));
        }
        $winner = (string) array_key_first($strongholds);

        return $builder
            ->withGameState(static function (GameState $state, Scenario $scenario) use ($winner, $condition): void {
                $state->winnerId = $scenario->player($winner)->getId();
                $state->victoryCondition = $condition;
            })
            ->build();
    }

    /**
     * The game becomes that of a match of a tournament between its players (persisted).
     */
    private function ofATournament(Scenario $scenario): void
    {
        $entityManager = self::service(EntityManagerInterface::class);
        $now = new \DateTimeImmutable('2026-11-20 20:00:00');
        $organizer = $entityManager->find(User::class, 'john') ?? throw new \LogicException();
        $tournament = new Tournament('tournament', $organizer, $now, str_repeat('k', 32), $now);
        $deck = $entityManager->find(Deck::class, AppFixtures::LION_STARTER_DECK) ?? throw new \LogicException();
        $entries = [];
        foreach ($scenario->game()->getPlayers() as $player) {
            $user = $player->getUser();
            self::assertInstanceOf(User::class, $user);
            $entries[] = new TournamentEntry($tournament, $user, $deck, $now);
        }
        $match = new TournamentMatch($tournament, 1, 1, $entries[0], $entries[1]);
        $match->setGame($scenario->game());
        $entityManager->persist($tournament);
        array_walk($entries, $entityManager->persist(...));
        $entityManager->persist($match);
        $entityManager->flush();
    }

    /**
     * A game of a tournament that has ended, won by jane against john.
     */
    private function endedInATournament(VictoryCondition $condition): Scenario
    {
        $scenario = $this->scenario()
            ->atPlace(Game::STATE_ENDED)
            ->withPlayer('jane', static fn (PlayerBuilder $player): PlayerBuilder => $player->stronghold('01-shiro-nishiyama'))
            ->withPlayer('john', static fn (PlayerBuilder $player): PlayerBuilder => $player->stronghold('01-city-of-the-open-hand'))
            ->withGameState(static function (GameState $state, Scenario $scenario) use ($condition): void {
                $state->winnerId = $scenario->player('jane')->getId();
                $state->victoryCondition = $condition;
            })
            ->persist();
        $this->ofATournament($scenario);

        return $scenario;
    }

    private static function training(GameBuilder $builder): GameBuilder
    {
        return $builder->training();
    }

    /**
     * @return list<array{string, Trophy}>
     */
    private function trophiesOf(Scenario $scenario): array
    {
        return array_map(
            static fn (array $earned): array => [$earned[0]->getUserId(), $earned[1]],
            self::service(GameTrophies::class)->trophiesEarnedIn($scenario->game()),
        );
    }

    /**
     * @return iterable<string, array{VictoryCondition}>
     */
    public static function provideWonByPlaying(): iterable
    {
        yield 'honor' => [VictoryCondition::HonorWin];
        yield 'dishonor' => [VictoryCondition::HonorLoss];
        yield 'conquest' => [VictoryCondition::StrongholdProvinceBroken];
    }

    #[DataProvider('provideWonByPlaying')]
    public function testTheWinnerOfAStandardGameEarnsTheTrophyOfTheClanOfTheirStronghold(VictoryCondition $condition): void
    {
        $scenario = $this->ended(['jane' => '01-shiro-nishiyama', 'harry' => '01-city-of-the-open-hand'], $condition);

        self::assertEquals([['jane', Trophy::ClanCrab]], $this->trophiesOf($scenario));
    }

    /**
     * @return iterable<string, array{VictoryCondition}>
     */
    public static function provideNotPlayedToTheEnd(): iterable
    {
        yield 'concession' => [VictoryCondition::Concession];
        yield 'abandonment' => [VictoryCondition::Abandonment];
    }

    #[DataProvider('provideNotPlayedToTheEnd')]
    public function testAVictoryByConcessionOrAbandonmentEarnsNothing(VictoryCondition $condition): void
    {
        $scenario = $this->ended(['jane' => '01-shiro-nishiyama', 'harry' => '01-city-of-the-open-hand'], $condition);

        self::assertSame([], $this->trophiesOf($scenario));
        $scenario = $this->ended(['jane' => '01-shiro-nishiyama', 'kyuden-bot' => '01-city-of-the-open-hand'], $condition, self::training(...));
        self::assertSame([], $this->trophiesOf($scenario));
    }

    public function testAFriendlyGameEarnsNoTrophyOfAClan(): void
    {
        $scenario = $this->ended(
            ['jane' => '01-shiro-nishiyama', 'harry' => '01-city-of-the-open-hand'],
            VictoryCondition::HonorWin,
            static fn (GameBuilder $builder): GameBuilder => $builder->friendly(),
        );

        self::assertSame([], $this->trophiesOf($scenario));
    }

    public function testAGameAgainstTheHouseBotIsFinishedWonOrLostAndEarnsNoTrophyOfAClan(): void
    {
        $won = $this->ended(['jane' => '01-shiro-nishiyama', 'kyuden-bot' => '01-city-of-the-open-hand'], VictoryCondition::StrongholdProvinceBroken, self::training(...));
        $lost = $this->ended(['kyuden-bot' => '01-city-of-the-open-hand', 'jane' => '01-shiro-nishiyama'], VictoryCondition::HonorWin, self::training(...));

        self::assertEquals([['jane', Trophy::HouseBotGame]], $this->trophiesOf($won));
        self::assertEquals([['jane', Trophy::HouseBotGame]], $this->trophiesOf($lost));
    }

    public function testAStandardGameWonAgainstAnotherBotEarnsTheTrophyOfAClan(): void
    {
        // the house bot is known by the game, not by its players: another bot plays in the queue like a human
        $scenario = $this->ended(['jane' => '01-shiro-nishiyama', 'akodo-bot' => '01-yojin-no-shiro'], VictoryCondition::HonorWin);

        self::assertEquals([['jane', Trophy::ClanCrab]], $this->trophiesOf($scenario));
    }

    public function testABotEarnsNothing(): void
    {
        $scenario = $this->ended(['akodo-bot' => '01-yojin-no-shiro', 'jane' => '01-shiro-nishiyama'], VictoryCondition::HonorWin);

        self::assertSame([], $this->trophiesOf($scenario));
    }

    public function testAReplayEarnsNothing(): void
    {
        $original = $this->ended(['jane' => '01-shiro-nishiyama', 'harry' => '01-city-of-the-open-hand'], VictoryCondition::HonorWin)->game();
        $replay = $this->ended(['jane' => '01-shiro-nishiyama', 'harry' => '01-city-of-the-open-hand'], VictoryCondition::HonorWin);
        $jane = $replay->player('jane')->getUser();
        self::assertInstanceOf(User::class, $jane);
        $replay->game()->markAsReplay($original, $jane);

        self::assertSame([], $this->trophiesOf($replay));
    }

    public function testTheTrophiesAreAwardedOnceWithTheirReward(): void
    {
        $first = $this->ended(['jane' => '01-mountain-s-anvil-castle', 'harry' => '01-city-of-the-open-hand'], VictoryCondition::HonorWin);
        $second = $this->ended(['jane' => '01-mountain-s-anvil-castle', 'harry' => '01-city-of-the-open-hand'], VictoryCondition::HonorWin);
        $trophies = self::service(GameTrophies::class);
        $at = new \DateTimeImmutable('2026-11-14 12:00:00');

        self::assertCount(1, $trophies->award($first->game(), $at));
        self::assertSame([], $trophies->award($second->game(), $at->modify('+1 day')), 'earned already');
    }

    /**
     * @return iterable<string, array{VictoryCondition}>
     */
    public static function provideEveryVictory(): iterable
    {
        yield from self::provideWonByPlaying();
        yield from self::provideNotPlayedToTheEnd();
    }

    #[DataProvider('provideEveryVictory')]
    public function testBothPlayersOfAGameOfATournamentEarnItsTrophyWonOrLost(VictoryCondition $condition): void
    {
        $trophies = $this->trophiesOf($this->endedInATournament($condition));

        self::assertContains(['jane', Trophy::TournamentGame], $trophies);
        self::assertContains(['john', Trophy::TournamentGame], $trophies, 'lost, conceded or not');
    }

    public function testAPlayerWhoLeftAGameOfATournamentWithoutConcedingEarnsNothingOfIt(): void
    {
        $scenario = $this->endedInATournament(VictoryCondition::Abandonment);
        self::service(PlayerStateManager::class)->withState($scenario->player('john'), static fn (PlayerState $state): PlayerState => $state->setAbandoned(true));

        self::assertSame([['jane', Trophy::TournamentGame]], $this->trophiesOf($scenario));
    }

    public function testAGameOfATournamentEndedWithoutAWinnerEarnsNothing(): void
    {
        // the platform has ended it, every player gone (EndAbandonedGame)
        $scenario = $this->endedInATournament(VictoryCondition::Abandonment);
        self::service(GameStateManager::class)->withState($scenario->game(), static function (GameState $state): void {
            $state->winnerId = null;
        });

        self::assertSame([], $this->trophiesOf($scenario));
    }

    public function testAGameOfNoTournamentEarnsNoTrophyOfTheTournaments(): void
    {
        $scenario = $this->ended(['jane' => '01-shiro-nishiyama', 'harry' => '01-city-of-the-open-hand'], VictoryCondition::Concession);

        self::assertSame([], $this->trophiesOf($scenario));
    }
}
