<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Exception\Rules\NoHonorBidException;
use App\Message\SetHonorBid;
use App\MessageHandler\SetHonorBidHandler;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class SetHonorBidHandlerTest extends AbstractKernelTestCase
{
    /**
     * @param \Closure(PlayerBuilder): mixed|null $john
     */
    private function scenarioAtRevealHonorDials(?\Closure $john = null): Scenario
    {
        return $this->scenario()
            ->atPlace('reveal_honor_dials')
            ->withPlayer('john', $john ?? fn (PlayerBuilder $p): PlayerBuilder => $p->honorBid(3))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->honorBid(1))
            ->persist();
    }

    private function setHonorBid(Scenario $scenario, int $bid): void
    {
        self::service(SetHonorBidHandler::class)(new SetHonorBid($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->player('john')->getId(), $bid));
    }

    public function testChangesTheBidOnTheHonorDial(): void
    {
        $scenario = $this->scenarioAtRevealHonorDials();

        $this->setHonorBid($scenario, 4);

        self::assertSame(4, $this->playerStateManager->getState($scenario->player('john'))->getHonorBid());
        self::assertSame(1, $this->playerStateManager->getState($scenario->player('jane'))->getHonorBid());
    }

    public function testAcceptsABidOfZero(): void
    {
        $scenario = $this->scenarioAtRevealHonorDials();

        $this->setHonorBid($scenario, 0);

        self::assertSame(0, $this->playerStateManager->getState($scenario->player('john'))->getHonorBid());
    }

    public function testRejectsAPlayerWhoHasNotBid(): void
    {
        $scenario = $this->scenarioAtRevealHonorDials(fn (PlayerBuilder $p): PlayerBuilder => $p);

        $this->expectException(NoHonorBidException::class);

        $this->setHonorBid($scenario, 2);
    }
}
