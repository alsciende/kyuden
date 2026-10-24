<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Exception\Rules\NotEnoughFateException;
use App\Message\LoseFate;
use App\MessageHandler\LoseFateHandler;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class LoseFateHandlerTest extends AbstractKernelTestCase
{
    private function scenarioWithFate(int $fate): Scenario
    {
        return $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->fate($fate))
            ->withPlayer('jane')
            ->persist();
    }

    private function loseFate(Scenario $scenario, int $fateLoss, string $sender = 'john'): void
    {
        self::service(LoseFateHandler::class)(new LoseFate($scenario->game()->getId(), $scenario->player($sender)->getId(), $scenario->player('john')->getId(), $fateLoss));
    }

    public function testTheTargetPlayerLosesFate(): void
    {
        $scenario = $this->scenarioWithFate(3);

        $this->loseFate($scenario, 2, sender: 'jane');

        self::assertSame(1, $this->playerStateManager->getState($scenario->player('john'))->getFate());
    }

    public function testCannotLoseMoreFateThanThePlayerHas(): void
    {
        $scenario = $this->scenarioWithFate(1);

        $this->expectException(NotEnoughFateException::class);

        $this->loseFate($scenario, 2);
    }
}
