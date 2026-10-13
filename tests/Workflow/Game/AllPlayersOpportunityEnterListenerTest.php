<?php

declare(strict_types=1);

namespace App\Tests\Workflow\Game;

use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use App\Workflow\Game\AllPlayersOpportunityEnterListener;
use Symfony\Component\Workflow\Event\EnterEvent;
use Symfony\Component\Workflow\Marking;

class AllPlayersOpportunityEnterListenerTest extends AbstractKernelTestCase
{
    private function enter(): Scenario
    {
        $scenario = $this->scenario()
            ->atPlace('honor_bid')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->passed())
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->passed())
            ->activePlayer('john')
            ->build();

        self::service(AllPlayersOpportunityEnterListener::class)(new EnterEvent($scenario->game(), new Marking()));

        return $scenario;
    }

    public function testEveryPlayerHasTheOpportunityToAct(): void
    {
        $scenario = $this->enter();

        $state = self::service(GameStateManager::class)->getState($scenario->game());
        self::assertTrue($state->isActivePlayer($scenario->player('john')));
        self::assertTrue($state->isActivePlayer($scenario->player('jane')));
    }

    public function testNoPlayerHasPassedWhenTheStepBegins(): void
    {
        $scenario = $this->enter();

        foreach (['john', 'jane'] as $alias) {
            self::assertFalse($this->playerStateManager->getState($scenario->player($alias))->isPassed());
        }
    }
}
