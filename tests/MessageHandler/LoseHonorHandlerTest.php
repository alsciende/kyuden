<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Message\LoseHonor;
use App\MessageHandler\LoseHonorHandler;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class LoseHonorHandlerTest extends AbstractKernelTestCase
{
    private function scenarioWithHonor(int $honor): Scenario
    {
        return $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->honor($honor))
            ->withPlayer('jane')
            ->persist();
    }

    private function loseHonor(Scenario $scenario, int $honorLoss, string $sender = 'john'): void
    {
        self::service(LoseHonorHandler::class)(new LoseHonor($scenario->game()->getId(), $scenario->player($sender)->getId(), $scenario->player('john')->getId(), $honorLoss));
    }

    public function testTheTargetPlayerLosesHonor(): void
    {
        $scenario = $this->scenarioWithHonor(10);

        $this->loseHonor($scenario, 2, sender: 'jane');

        self::assertSame(8, $this->playerStateManager->getState($scenario->player('john'))->getHonor());
    }

    public function testSubtractsTheLossFromTheCurrentHonor(): void
    {
        $scenario = $this->scenarioWithHonor(10);

        $this->loseHonor($scenario, 2);

        self::assertSame(8, $this->playerStateManager->getState($scenario->player('john'))->getHonor());
    }

    public function testRejectsANegativeLoss(): void
    {
        $scenario = $this->scenarioWithHonor(10);

        $this->expectException(\InvalidArgumentException::class);

        $this->loseHonor($scenario, -2);
    }

    public function testCannotLoseMoreHonorThanThePlayerHas(): void
    {
        $scenario = $this->scenarioWithHonor(2);

        $this->loseHonor($scenario, 3);

        self::assertSame(0, $this->playerStateManager->getState($scenario->player('john'))->getHonor());
    }
}
