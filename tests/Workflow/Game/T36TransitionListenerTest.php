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
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->fate(3)->fateCollected()->dynastyPassed())
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->fateCollected()->dynastyPassed())
            ->build();

        self::service(Registry::class)->get($scenario->game())->apply($scenario->game(), 't36');

        foreach (['john', 'jane'] as $alias) {
            $state = $this->playerStateManager->getState($scenario->player($alias));
            self::assertFalse($state->isFateCollected());
            self::assertFalse($state->isDynastyPassed());
        }
        self::assertSame(3, $this->playerStateManager->getState($scenario->player('john'))->getFate());
    }
}
