<?php

declare(strict_types=1);

namespace App\Tests\Middleware;

use App\Exception\Api\StaleVersionException;
use App\Message\LoseHonor;
use App\Middleware\Stamp\ExpectedVersionStamp;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class GameVersionMiddlewareTest extends AbstractKernelTestCase
{
    private function scenarioWithJohn(): Scenario
    {
        return $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10))
            ->persist();
    }

    private function loseHonor(Scenario $scenario): LoseHonor
    {
        return new LoseHonor($scenario->game()->getId(), $scenario->player('john')->getId(), $scenario->player('john')->getId(), 1);
    }

    public function testAcceptsAMessageSentForTheCurrentVersion(): void
    {
        $scenario = $this->scenarioWithJohn();

        $this->messageBus->dispatch($this->loseHonor($scenario), [new ExpectedVersionStamp(0)]);

        self::assertSame(9, $this->playerStateManager->getState($scenario->player('john'))->getHonor());
        self::assertSame(1, $scenario->game()->getVersion());
    }

    public function testRejectsAMessageSentForAPreviousVersion(): void
    {
        $scenario = $this->scenarioWithJohn();
        $this->messageBus->dispatch($this->loseHonor($scenario));

        try {
            $this->messageBus->dispatch($this->loseHonor($scenario), [new ExpectedVersionStamp(0)]);
            self::fail('The message should have been rejected.');
        } catch (StaleVersionException) {
        }

        self::assertSame(9, $this->playerStateManager->getState($scenario->player('john'))->getHonor());
        self::assertSame(1, $scenario->game()->getVersion());
    }
}
