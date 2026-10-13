<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Enum\VictoryCondition;
use App\Model\Victory;
use App\Service\VictoryService;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class VictoryServiceTest extends AbstractKernelTestCase
{
    private function scenarioWithHonor(int $johnHonor, int $janeHonor, string $firstPlayer = 'john'): Scenario
    {
        return $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->honor($johnHonor))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->honor($janeHonor))
            ->firstPlayer($firstPlayer)
            ->build();
    }

    private function findVictory(Scenario $scenario): ?Victory
    {
        return self::service(VictoryService::class)->findVictory($scenario->game());
    }

    public function testNoVictoryBetweenTheLimits(): void
    {
        self::assertNull($this->findVictory($this->scenarioWithHonor(1, 24)));
    }

    public function testAPlayerWithTwentyFiveHonorWins(): void
    {
        $scenario = $this->scenarioWithHonor(10, 25);

        $victory = $this->findVictory($scenario);

        self::assertSame($scenario->player('jane'), $victory?->winner);
        self::assertSame(VictoryCondition::HonorWin, $victory->condition);
    }

    public function testTheOpponentOfAPlayerWithNoHonorWins(): void
    {
        $scenario = $this->scenarioWithHonor(0, 10);

        $victory = $this->findVictory($scenario);

        self::assertSame($scenario->player('jane'), $victory?->winner);
        self::assertSame(VictoryCondition::HonorLoss, $victory->condition);
    }

    public function testTheFirstPlayerWinsWhenBothPlayersMeetAConditionAtOnce(): void
    {
        $scenario = $this->scenarioWithHonor(25, 25, firstPlayer: 'jane');

        self::assertSame($scenario->player('jane'), $this->findVictory($scenario)?->winner);
    }

    public function testNoVictoryBeforeTheHonorIsSet(): void
    {
        $scenario = $this->scenario()
            ->atPlace('choose_stronghold')
            ->withPlayer('john')
            ->withPlayer('jane')
            ->build();

        self::assertNull($this->findVictory($scenario));
    }
}
