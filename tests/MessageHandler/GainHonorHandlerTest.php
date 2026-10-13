<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Message\GainHonor;
use App\MessageHandler\GainHonorHandler;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;

class GainHonorHandlerTest extends AbstractKernelTestCase
{
    public function testAddsTheGainToTheCurrentHonor(): void
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10))
            ->withPlayer('jane')
            ->persist();

        self::service(GainHonorHandler::class)(new GainHonor($scenario->game()->getId(), $scenario->player('john')->getId(), 2));

        self::assertSame(12, $this->playerStateManager->getState($scenario->player('john'))->getHonor());
    }

    public function testRejectsANegativeGain(): void
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10))
            ->withPlayer('jane')
            ->persist();

        $this->expectException(\InvalidArgumentException::class);

        self::service(GainHonorHandler::class)(new GainHonor($scenario->game()->getId(), $scenario->player('john')->getId(), -2));
    }
}
