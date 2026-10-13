<?php

declare(strict_types=1);

namespace App\Tests\Workflow\Game;

use App\Enum\Orientation;
use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Workflow\Game\DefendingPlayerOpportunityEnterListener;
use Symfony\Component\Workflow\Event\EnterEvent;
use Symfony\Component\Workflow\Marking;

class DefendingPlayerOpportunityEnterListenerTest extends AbstractKernelTestCase
{
    public function testTheDefendingPlayerHasTheFirstOpportunityToActAndNoPlayerHasPassed(): void
    {
        $scenario = $this->scenario()
            ->atPlace('declare_defenders')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->passed())
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->passed()->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceUp))
            ->conflict('john', 'province')
            ->activePlayer('john')
            ->build();

        self::service(DefendingPlayerOpportunityEnterListener::class)(new EnterEvent($scenario->game(), new Marking()));

        self::assertSame([$scenario->player('jane')->getId()], self::service(GameStateManager::class)->getState($scenario->game())->activePlayerIds);
        foreach (['john', 'jane'] as $alias) {
            self::assertFalse($this->playerStateManager->getState($scenario->player($alias))->isPassed());
        }
    }
}
