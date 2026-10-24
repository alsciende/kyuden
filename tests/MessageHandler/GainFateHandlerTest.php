<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Message\GainFate;
use App\MessageHandler\GainFateHandler;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;

class GainFateHandlerTest extends AbstractKernelTestCase
{
    public function testTheTargetPlayerGainsFate(): void
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->fate(3))
            ->withPlayer('jane')
            ->persist();

        self::service(GainFateHandler::class)(new GainFate($scenario->game()->getId(), $scenario->player('jane')->getId(), $scenario->player('john')->getId(), 1));

        self::assertSame(4, $this->playerStateManager->getState($scenario->player('john'))->getFate());
    }
}
