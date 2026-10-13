<?php

declare(strict_types=1);

namespace App\Tests\Workflow\Game;

use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use Symfony\Component\Workflow\Registry;

class EndGameGuardListenerTest extends AbstractKernelTestCase
{
    private function canEndGame(int $johnHonor): bool
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->honor($johnHonor))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10))
            ->build();

        return self::service(Registry::class)->get($scenario->game())->can($scenario->game(), 'end_game');
    }

    public function testBlockedWhileNoPlayerHasMetAVictoryCondition(): void
    {
        self::assertFalse($this->canEndGame(12));
    }

    public function testAllowedOnceAPlayerHasMetAVictoryCondition(): void
    {
        self::assertTrue($this->canEndGame(0));
    }
}
