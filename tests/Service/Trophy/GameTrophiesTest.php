<?php

declare(strict_types=1);

namespace App\Tests\Service\Trophy;

use App\Entity\Game;
use App\Entity\User;
use App\Enum\Trophy;
use App\Enum\VictoryCondition;
use App\Service\Trophy\GameTrophies;
use App\State\Model\GameState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\GameBuilder;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
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
}
