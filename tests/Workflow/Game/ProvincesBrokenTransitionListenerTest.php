<?php

declare(strict_types=1);

namespace App\Tests\Workflow\Game;

use App\Entity\CardTypes\ProvinceCard;
use App\Enum\Orientation;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use App\Workflow\Game\ProvincesBrokenTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Workflow\Marking;

class ProvincesBrokenTransitionListenerTest extends AbstractKernelTestCase
{
    private function breakProvinces(string $winner, int $attackerSkill, int $defenderSkill): Scenario
    {
        $scenario = $this->scenario()
            ->atPlace('break_province')
            ->withPlayer('john')
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceUp)
                ->province('01-entrenched-position', as: 'other-province', orientation: Orientation::FaceUp))
            ->conflict('john', 'province')
            ->withGameState(function (GameState $state, Scenario $scenario) use ($winner, $attackerSkill, $defenderSkill): void {
                $conflict = $state->currentConflict();
                $conflict->attackedProvinceIds[] = $scenario->card('other-province')->getId();
                $conflict->provinceStrengths = [$scenario->card('province')->getId() => 3, $scenario->card('other-province')->getId() => 5];
                $conflict->attackerSkill = $attackerSkill;
                $conflict->defenderSkill = $defenderSkill;
                $conflict->winnerId = $scenario->player($winner)->getId();
            })
            ->persist();

        self::service(ProvincesBrokenTransitionListener::class)(new TransitionEvent($scenario->game(), new Marking()));

        return $scenario;
    }

    public function testEachAttackedProvinceIsBrokenWhenTheExcessSkillReachesItsStrength(): void
    {
        $scenario = $this->breakProvinces('john', 4, 1);

        self::assertSame(ProvinceCard::STATE_BROKEN, $scenario->card('province')->getCurrentPlace());
        self::assertSame(ProvinceCard::STATE_UNBROKEN, $scenario->card('other-province')->getCurrentPlace());
        self::assertSame([$scenario->card('province')->getId()], self::service(GameStateManager::class)->getState($scenario->game())->currentConflict()->brokenProvinceIds);
    }

    public function testNoProvinceIsBrokenWhenTheDefendingPlayerWins(): void
    {
        $scenario = $this->breakProvinces('jane', 0, 4);

        self::assertSame(ProvinceCard::STATE_UNBROKEN, $scenario->card('province')->getCurrentPlace());
    }
}
