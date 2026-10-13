<?php

declare(strict_types=1);

namespace App\Tests\Workflow\Game;

use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use Symfony\Component\Workflow\Registry;

class HonorBidsConfirmedGuardListenerTest extends AbstractKernelTestCase
{
    private function canLeaveHonorBid(?int $johnBid, ?int $janeBid): bool
    {
        $scenario = $this->scenario()
            ->atPlace('honor_bid')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $johnBid !== null ? $p->honorBid($johnBid) : $p)
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $janeBid !== null ? $p->honorBid($janeBid) : $p)
            ->build();

        return self::service(Registry::class)->get($scenario->game())->can($scenario->game(), 'honor_bids_confirmed');
    }

    public function testBlockedWhileAPlayerHasNotBid(): void
    {
        self::assertFalse($this->canLeaveHonorBid(johnBid: 3, janeBid: null));
    }

    public function testAllowedOnceEveryPlayerHasBid(): void
    {
        self::assertTrue($this->canLeaveHonorBid(johnBid: 3, janeBid: 1));
    }
}
