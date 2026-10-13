<?php

declare(strict_types=1);

namespace App\Tests\Workflow\Game;

use App\Exception\Rules\NoHonorBidException;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Symfony\Component\Workflow\Registry;

class HonorTransferredTransitionListenerTest extends AbstractKernelTestCase
{
    private function transferHonor(?int $johnBid, ?int $janeBid): Scenario
    {
        $scenario = $this->scenario()
            ->atPlace('transfer_honor')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $johnBid === null ? $p->honor(10) : $p->honor(10)->honorBid($johnBid))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $janeBid === null ? $p->honor(10) : $p->honor(10)->honorBid($janeBid))
            ->build();

        self::service(Registry::class)->get($scenario->game())->apply($scenario->game(), 'honor_transferred');

        return $scenario;
    }

    private function honor(Scenario $scenario, string $alias): ?int
    {
        return $this->playerStateManager->getState($scenario->player($alias))->getHonor();
    }

    public function testTheHigherBidderGivesTheDifferenceToTheLowerBidder(): void
    {
        $scenario = $this->transferHonor(5, 2);

        self::assertSame(7, $this->honor($scenario, 'john'));
        self::assertSame(13, $this->honor($scenario, 'jane'));
    }

    public function testTheOrderOfThePlayersDoesNotMatter(): void
    {
        $scenario = $this->transferHonor(1, 4);

        self::assertSame(13, $this->honor($scenario, 'john'));
        self::assertSame(7, $this->honor($scenario, 'jane'));
    }

    public function testNoHonorIsTransferredWhenTheBidsAreEqual(): void
    {
        $scenario = $this->transferHonor(3, 3);

        self::assertSame(10, $this->honor($scenario, 'john'));
        self::assertSame(10, $this->honor($scenario, 'jane'));
    }

    public function testABidCanBeZero(): void
    {
        $scenario = $this->transferHonor(0, 5);

        self::assertSame(15, $this->honor($scenario, 'john'));
        self::assertSame(5, $this->honor($scenario, 'jane'));
    }

    public function testEveryPlayerMustHaveBid(): void
    {
        $this->expectException(NoHonorBidException::class);

        $this->transferHonor(3, null);
    }
}
