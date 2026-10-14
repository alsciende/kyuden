<?php

declare(strict_types=1);

namespace App\Tests\Workflow\Game;

use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Workflow\Game\HonorBidsConfirmedTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Workflow\Marking;

class HonorBidsConfirmedTransitionListenerTest extends AbstractKernelTestCase
{
    public function testTheSecretBidsAreRevealed(): void
    {
        $scenario = $this->scenario()
            ->atPlace('honor_bid')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->pendingHonorBid(3))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->pendingHonorBid(1))
            ->build();

        self::service(HonorBidsConfirmedTransitionListener::class)(new TransitionEvent($scenario->game(), new Marking()));

        foreach (['john' => 3, 'jane' => 1] as $alias => $bid) {
            $state = $this->playerStateManager->getState($scenario->player($alias));
            self::assertSame($bid, $state->getHonorBid());
            self::assertNull($state->getPendingHonorBid());
        }
    }
}
