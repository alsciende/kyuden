<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Enum\Orientation;
use App\Exception\Rules\NotAttackedProvinceException;
use App\Exception\Rules\NotDefendingPlayerException;
use App\Message\DeclareProvinceStrength;
use App\MessageHandler\DeclareProvinceStrengthHandler;
use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class DeclareProvinceStrengthHandlerTest extends AbstractKernelTestCase
{
    private function scenarioAtDetermineResult(): Scenario
    {
        return $this->scenario()
            ->atPlace('determine_result')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceUp)
                ->province('01-entrenched-position', as: 'other-province', orientation: Orientation::FaceUp))
            ->conflict('john', 'province', attackers: ['toturi'])
            ->persist();
    }

    private function declare(Scenario $scenario, string $player, string $province, int $strength): void
    {
        self::service(DeclareProvinceStrengthHandler::class)(new DeclareProvinceStrength(
            $scenario->game()->getId(),
            $scenario->player($player)->getId(),
            $scenario->card($province)->getId(),
            $strength,
        ));
    }

    public function testTheDefendingPlayerDeclaresTheStrengthOfAnAttackedProvince(): void
    {
        $scenario = $this->scenarioAtDetermineResult();

        $this->declare($scenario, 'jane', 'province', 7);

        $conflict = self::service(GameStateManager::class)->getState($scenario->game())->currentConflict();
        self::assertSame([$scenario->card('province')->getId() => 7], $conflict->provinceStrengths);
    }

    public function testRejectsTheAttackingPlayer(): void
    {
        $scenario = $this->scenarioAtDetermineResult();

        $this->expectException(NotDefendingPlayerException::class);

        $this->declare($scenario, 'john', 'province', 1);
    }

    public function testRejectsAProvinceThatIsNotAttacked(): void
    {
        $scenario = $this->scenarioAtDetermineResult();

        $this->expectException(NotAttackedProvinceException::class);

        $this->declare($scenario, 'jane', 'other-province', 1);
    }
}
