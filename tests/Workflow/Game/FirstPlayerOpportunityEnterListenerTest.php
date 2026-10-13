<?php

declare(strict_types=1);

namespace App\Tests\Workflow\Game;

use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use App\Workflow\Game\FirstPlayerOpportunityEnterListener;
use Symfony\Component\Workflow\Event\EnterEvent;
use Symfony\Component\Workflow\Marking;

class FirstPlayerOpportunityEnterListenerTest extends AbstractKernelTestCase
{
    private function enter(Scenario $scenario): void
    {
        self::service(FirstPlayerOpportunityEnterListener::class)(new EnterEvent($scenario->game(), new Marking()));
    }

    public function testTheFirstPlayerHasTheFirstOpportunityToAct(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_phase_begins')
            ->withPlayer('john')
            ->withPlayer('jane')
            ->firstPlayer('jane')
            ->activePlayer('john')
            ->build();

        $this->enter($scenario);

        $state = self::service(GameStateManager::class)->getState($scenario->game());
        self::assertSame([$scenario->player('jane')->getId()], $state->activePlayerIds);
    }

    public function testNoPlayerHasPassedWhenTheStepBegins(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_phase_begins')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->passed())
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->passed())
            ->build();

        $this->enter($scenario);

        foreach (['john', 'jane'] as $alias) {
            self::assertFalse($this->playerStateManager->getState($scenario->player($alias))->isPassed());
        }
    }
}
