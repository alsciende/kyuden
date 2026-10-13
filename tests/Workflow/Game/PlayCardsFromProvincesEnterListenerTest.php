<?php

declare(strict_types=1);

namespace App\Tests\Workflow\Game;

use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Symfony\Component\Workflow\Registry;

class PlayCardsFromProvincesEnterListenerTest extends AbstractKernelTestCase
{
    private function enterPlayCardsFromProvinces(Scenario $scenario): void
    {
        self::service(Registry::class)->get($scenario->game())->apply($scenario->game(), 'fate_collected');
    }

    public function testTheFirstPlayerHasTheFirstOpportunityToAct(): void
    {
        $scenario = $this->scenario()
            ->atPlace('collect_fate')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->fateCollected())
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->fateCollected())
            ->firstPlayer('jane')
            ->activePlayer('john')
            ->build();

        $this->enterPlayCardsFromProvinces($scenario);

        $state = self::service(GameStateManager::class)->getState($scenario->game());
        self::assertTrue($state->isActivePlayer($scenario->player('jane')));
    }

    public function testNoPlayerHasPassedWhenTheStepBegins(): void
    {
        $scenario = $this->scenario()
            ->atPlace('collect_fate')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->fateCollected()->passed())
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->fateCollected()->passed())
            ->build();

        $this->enterPlayCardsFromProvinces($scenario);

        foreach (['john', 'jane'] as $alias) {
            self::assertFalse($this->playerStateManager->getState($scenario->player($alias))->isPassed());
        }
    }
}
