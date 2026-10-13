<?php

declare(strict_types=1);

namespace App\Tests\Workflow\Game;

use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Symfony\Component\Workflow\Registry;

class RevealHonorDialsEnterListenerTest extends AbstractKernelTestCase
{
    private function revealHonorDials(): Scenario
    {
        $scenario = $this->scenario()
            ->atPlace('honor_bid')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->honorBid(2)->passed())
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->honorBid(4)->passed())
            ->build();

        self::service(Registry::class)->get($scenario->game())->apply($scenario->game(), 'honor_bids_confirmed');

        return $scenario;
    }

    public function testEveryPlayerHasTheOpportunityToAct(): void
    {
        $scenario = $this->revealHonorDials();

        $state = self::service(GameStateManager::class)->getState($scenario->game());
        self::assertTrue($state->isActivePlayer($scenario->player('john')));
        self::assertTrue($state->isActivePlayer($scenario->player('jane')));
    }

    public function testNoPlayerHasPassedWhenTheStepBegins(): void
    {
        $scenario = $this->revealHonorDials();

        foreach (['john', 'jane'] as $alias) {
            self::assertFalse($this->playerStateManager->getState($scenario->player($alias))->isPassed());
        }
    }
}
