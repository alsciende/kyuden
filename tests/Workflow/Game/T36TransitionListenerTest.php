<?php

declare(strict_types=1);

namespace App\Tests\Workflow\Game;

use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use Symfony\Component\Workflow\Registry;

class T36TransitionListenerTest extends AbstractKernelTestCase
{
    public function testTurnRecordsAreResetAtTheEndOfTheRound(): void
    {
        $scenario = $this->scenario()
            ->atPlace('fate_phase_ends')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->fate(3)->fateCollected())
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->fateCollected())
            ->build();

        self::service(Registry::class)->get($scenario->game())->apply($scenario->game(), 't36');

        foreach (['john', 'jane'] as $alias) {
            self::assertFalse($this->playerStateManager->getState($scenario->player($alias))->isFateCollected());
        }
        self::assertSame(3, $this->playerStateManager->getState($scenario->player('john'))->getFate());
    }
}
