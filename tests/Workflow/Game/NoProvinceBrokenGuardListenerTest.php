<?php

declare(strict_types=1);

namespace App\Tests\Workflow\Game;

use App\State\Model\GameState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use Symfony\Component\Workflow\Registry;

class NoProvinceBrokenGuardListenerTest extends AbstractKernelTestCase
{
    private function can(bool $broken): bool
    {
        $scenario = $this->scenario()
            ->atPlace('react_to_broken_province')
            ->withPlayer('john')
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->province('01-entrenched-position', as: 'province'))
            ->conflict('john', 'province')
            ->withGameState(function (GameState $state, Scenario $scenario) use ($broken): void {
                $state->currentConflict()->brokenProvinceIds = $broken ? [$scenario->card('province')->getId()] : [];
            })
            ->build();

        return self::service(Registry::class)->get($scenario->game())->can($scenario->game(), 'no_province_broken');
    }

    public function testTheReactionsAreSkippedWhenNoProvinceIsBroken(): void
    {
        self::assertTrue($this->can(false));
    }

    public function testThePlayersMayReactToABrokenProvince(): void
    {
        self::assertFalse($this->can(true));
    }
}
