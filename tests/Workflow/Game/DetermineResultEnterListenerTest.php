<?php

declare(strict_types=1);

namespace App\Tests\Workflow\Game;

use App\Enum\Orientation;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use App\Workflow\Game\DetermineResultEnterListener;
use Symfony\Component\Workflow\Event\EnterEvent;
use Symfony\Component\Workflow\Marking;

class DetermineResultEnterListenerTest extends AbstractKernelTestCase
{
    public function testAttackedProvincesHaveTheirPrintedStrengthAndTheStrongholdBonusForTheStrongholdProvince(): void
    {
        $scenario = $this->scenario()
            ->atPlace('conflict_action_window')
            ->withPlayer('john')
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->stronghold('01-yojin-no-shiro', as: 'stronghold')
                ->strongholdProvince('01-ancestral-lands', as: 'stronghold-province')
                ->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceUp))
            ->conflict('john', 'stronghold-province')
            ->withGameState(function (GameState $state, Scenario $scenario): void {
                $state->currentConflict()->attackedProvinceIds[] = $scenario->card('province')->getId();
            })
            ->persist();

        self::service(DetermineResultEnterListener::class)(new EnterEvent($scenario->game(), new Marking()));

        $strength = fn (string $alias): int => (int) $scenario->card($alias)->getLogicalCard()->getStrength();
        $bonus = (int) $scenario->card('stronghold')->getLogicalCard()->getStrengthBonus();
        self::assertNotSame(0, $bonus);
        self::assertSame([
            $scenario->card('stronghold-province')->getId() => $strength('stronghold-province') + $bonus,
            $scenario->card('province')->getId() => $strength('province'),
        ], self::service(GameStateManager::class)->getState($scenario->game())->currentConflict()->provinceStrengths);
    }
}
