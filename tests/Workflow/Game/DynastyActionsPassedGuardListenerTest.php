<?php

declare(strict_types=1);

namespace App\Tests\Workflow\Game;

use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use Symfony\Component\Workflow\Registry;

class DynastyActionsPassedGuardListenerTest extends AbstractKernelTestCase
{
    private function canLeavePlayCardsFromProvinces(bool $johnPassed, bool $janePassed): bool
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $johnPassed ? $p->passed() : $p)
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $janePassed ? $p->passed() : $p)
            ->build();

        return self::service(Registry::class)->get($scenario->game())->can($scenario->game(), 'dynasty_actions_passed');
    }

    public function testBlockedWhileAPlayerHasNotPassed(): void
    {
        self::assertFalse($this->canLeavePlayCardsFromProvinces(johnPassed: true, janePassed: false));
    }

    public function testAllowedOnceEveryPlayerHasPassed(): void
    {
        self::assertTrue($this->canLeavePlayCardsFromProvinces(johnPassed: true, janePassed: true));
    }
}
