<?php

declare(strict_types=1);

namespace App\Tests\Workflow\Game;

use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Workflow\Game\HonorBidEnterListener;
use Symfony\Component\Workflow\Event\EnterEvent;
use Symfony\Component\Workflow\Marking;

class HonorBidEnterListenerTest extends AbstractKernelTestCase
{
    public function testThePreviousBidsAreForgottenWhenANewBidBegins(): void
    {
        $scenario = $this->scenario()
            ->atPlace('honor_bid')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->honorBid(3))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->honorBid(1)->pendingHonorBid(2))
            ->build();

        self::service(HonorBidEnterListener::class)(new EnterEvent($scenario->game(), new Marking()));

        foreach (['john', 'jane'] as $alias) {
            $state = $this->playerStateManager->getState($scenario->player($alias));
            self::assertNull($state->getHonorBid());
            self::assertNull($state->getPendingHonorBid());
        }
    }
}
