<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Exception\Rules\HonorAlreadySetException;
use App\Message\SetHonor;
use App\MessageHandler\SetHonorHandler;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class SetHonorHandlerTest extends AbstractKernelTestCase
{
    private function setHonor(Scenario $scenario, int $honor): void
    {
        self::service(SetHonorHandler::class)(new SetHonor($scenario->game()->getId(), $scenario->player('john')->getId(), $honor));
    }

    public function testSetsTheHonorOfAPlayerWithoutHonor(): void
    {
        $scenario = $this->scenario()
            ->atPlace('choose_stronghold')
            ->withPlayer('john')
            ->withPlayer('jane')
            ->persist();

        $this->setHonor($scenario, 12);

        self::assertSame(12, $this->playerStateManager->getState($scenario->player('john'))->getHonor());
    }

    public function testRejectsAPlayerWhoseHonorIsAlreadySet(): void
    {
        $scenario = $this->scenario()
            ->atPlace('choose_stronghold')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10))
            ->withPlayer('jane')
            ->persist();

        $this->expectException(HonorAlreadySetException::class);

        $this->setHonor($scenario, 12);
    }
}
