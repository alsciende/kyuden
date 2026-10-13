<?php

declare(strict_types=1);

namespace App\Tests\Workflow\Game;

use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use Symfony\Component\Workflow\Registry;

class FateCollectedGuardListenerTest extends AbstractKernelTestCase
{
    private function canLeaveCollectFate(bool $johnCollected, bool $janeCollected): bool
    {
        $scenario = $this->scenario()
            ->atPlace('collect_fate')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $johnCollected ? $p->fateCollected() : $p)
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $janeCollected ? $p->fateCollected() : $p)
            ->build();

        return self::service(Registry::class)->get($scenario->game())->can($scenario->game(), 'fate_collected');
    }

    public function testBlockedWhileAPlayerHasNotCollectedFate(): void
    {
        self::assertFalse($this->canLeaveCollectFate(johnCollected: true, janeCollected: false));
    }

    public function testAllowedOnceEveryPlayerHasCollectedFate(): void
    {
        self::assertTrue($this->canLeaveCollectFate(johnCollected: true, janeCollected: true));
    }
}
