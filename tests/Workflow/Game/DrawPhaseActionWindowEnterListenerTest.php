<?php

declare(strict_types=1);

namespace App\Tests\Workflow\Game;

use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use App\Workflow\Game\DrawPhaseActionWindowEnterListener;
use Symfony\Component\Workflow\Event\EnterEvent;
use Symfony\Component\Workflow\Marking;

class DrawPhaseActionWindowEnterListenerTest extends AbstractKernelTestCase
{
    private function enterDrawPhaseActionWindow(Scenario $scenario): void
    {
        self::service(DrawPhaseActionWindowEnterListener::class)(new EnterEvent($scenario->game(), new Marking()));
    }

    public function testTheFirstPlayerHasTheFirstOpportunityToAct(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_cards')
            ->withPlayer('john')
            ->withPlayer('jane')
            ->firstPlayer('jane')
            ->activePlayer('john')
            ->build();

        $this->enterDrawPhaseActionWindow($scenario);

        $state = self::service(GameStateManager::class)->getState($scenario->game());
        self::assertSame([$scenario->player('jane')->getId()], $state->activePlayerIds);
    }

    public function testNoPlayerHasPassedWhenTheWindowOpens(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_cards')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->passed())
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->passed())
            ->build();

        $this->enterDrawPhaseActionWindow($scenario);

        foreach (['john', 'jane'] as $alias) {
            self::assertFalse($this->playerStateManager->getState($scenario->player($alias))->isPassed());
        }
    }
}
