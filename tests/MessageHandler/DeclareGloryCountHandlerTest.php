<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Exception\Rules\GloryCountAlreadyDeclaredException;
use App\Message\DeclareGloryCount;
use App\MessageHandler\DeclareGloryCountHandler;
use App\State\Manager\PlayerStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\Scenario;

class DeclareGloryCountHandlerTest extends AbstractKernelTestCase
{
    private function scenarioAtGloryCount(): Scenario
    {
        return $this->scenario()
            ->atPlace('glory_count')
            ->withPlayer('john')
            ->withPlayer('jane')
            ->persist();
    }

    private function declare(Scenario $scenario, string $player, int $glory): void
    {
        self::service(DeclareGloryCountHandler::class)(new DeclareGloryCount($scenario->game()->getId(), $scenario->player($player)->getId(), $glory));
    }

    public function testEachPlayerDeclaresTheirGloryCount(): void
    {
        $scenario = $this->scenarioAtGloryCount();

        $this->declare($scenario, 'jane', 3);

        self::assertSame(3, self::service(PlayerStateManager::class)->getState($scenario->player('jane'))->getGloryCount());
    }

    public function testRejectsAPlayerWhoHasDeclaredTheirGloryCount(): void
    {
        $scenario = $this->scenarioAtGloryCount();
        $this->declare($scenario, 'jane', 3);

        $this->expectException(GloryCountAlreadyDeclaredException::class);

        $this->declare($scenario, 'jane', 4);
    }
}
