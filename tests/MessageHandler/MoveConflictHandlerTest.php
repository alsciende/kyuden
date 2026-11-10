<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\CardTypes\ProvinceCard;
use App\Enum\Orientation;
use App\Exception\Rules\NoConflictException;
use App\Exception\Rules\ProvinceNotAttackableException;
use App\Message\MoveConflict;
use App\MessageHandler\MoveConflictHandler;
use App\State\Manager\GameStateManager;
use App\State\Model\ConflictState;
use App\State\Model\GameState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class MoveConflictHandlerTest extends AbstractKernelTestCase
{
    /**
     * @param \Closure(GameState, Scenario): void|null $change
     */
    private function scenarioWithConflict(bool $conflict = true, ?\Closure $change = null): Scenario
    {
        $builder = $this->scenario()
            ->atPlace($conflict ? 'conflict_action_window' : 'draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['toturi' => '01-akodo-toturi'])
                ->province('01-entrenched-position', as: 'johns-province', orientation: Orientation::FaceUp))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceUp)
                ->province('01-the-art-of-peace', as: 'other')
                ->province('01-night-raid', as: 'third', orientation: Orientation::FaceUp)
                ->province('01-night-raid', as: 'broken', orientation: Orientation::UpsideDown));
        if ($conflict) {
            $builder->conflict('john', 'province', attackers: ['toturi']);
        }
        if ($change !== null) {
            $builder->withGameState($change);
        }

        return $builder->persist();
    }

    private function move(Scenario $scenario, string $province): void
    {
        // the defending player sends it (Talisman of the Sun): any player can
        self::service(MoveConflictHandler::class)(new MoveConflict(
            $scenario->game()->getId(),
            $scenario->player('jane')->getId(),
            $scenario->card($province)->getId(),
        ));
    }

    private function conflict(Scenario $scenario): ConflictState
    {
        return self::service(GameStateManager::class)->getState($scenario->game())->currentConflict();
    }

    public function testTheConflictMovesToAFacedownProvinceThatAPlayerReveals(): void
    {
        $scenario = $this->scenarioWithConflict();

        $this->move($scenario, 'other');

        $conflict = $this->conflict($scenario);
        $otherId = $scenario->card('other')->getId();
        self::assertSame($otherId, $conflict->provinceId);
        self::assertSame([$otherId], $conflict->attackedProvinceIds);
        // revealed by RevealProvince, so that the players see it
        self::assertSame(ProvinceCard::STATE_FACEDOWN, $scenario->card('other')->getCurrentPlace());
        self::assertSame([
            'jane moves the conflict from Entrenched Position to a facedown card',
        ], $this->logLines($scenario->game(), $scenario->player('john')));
    }

    public function testTheOtherAttackedProvincesStayAttacked(): void
    {
        // A War on Two Fronts, then Golden Plains
        $scenario = $this->scenarioWithConflict(change: function (GameState $state, Scenario $scenario): void {
            $state->currentConflict()->attackedProvinceIds[] = $scenario->card('third')->getId();
        });

        $this->move($scenario, 'other');

        self::assertSame([$scenario->card('other')->getId(), $scenario->card('third')->getId()], $this->conflict($scenario)->attackedProvinceIds);
    }

    public function testOnceRecordedTheStrengthOfTheNewProvinceReplacesTheOldOne(): void
    {
        $scenario = $this->scenarioWithConflict(change: function (GameState $state, Scenario $scenario): void {
            $id = $scenario->card('province')->getId();
            $state->currentConflict()->provinceStrengths = [$id => 2];
            $state->currentConflict()->declaredStrengthProvinceIds = [$id];
        });

        $this->move($scenario, 'third');

        $conflict = $this->conflict($scenario);
        self::assertSame([$scenario->card('third')->getId() => 4], $conflict->provinceStrengths);
        self::assertSame([], $conflict->declaredStrengthProvinceIds);
        self::assertFalse($conflict->strengthsDeclared());
        self::assertSame([
            'jane moves the conflict from Entrenched Position to Night Raid',
            'Night Raid has a strength of 4',
        ], $this->logLines($scenario->game(), $scenario->player('john')));
    }

    public function testRejectsABrokenProvince(): void
    {
        $scenario = $this->scenarioWithConflict();

        $this->expectException(ProvinceNotAttackableException::class);

        $this->move($scenario, 'broken');
    }

    public function testRejectsAProvinceOfTheAttackingPlayer(): void
    {
        $scenario = $this->scenarioWithConflict();

        $this->expectException(ProvinceNotAttackableException::class);

        $this->move($scenario, 'johns-province');
    }

    public function testRejectsAnAttackedProvince(): void
    {
        $scenario = $this->scenarioWithConflict();

        $this->expectException(ProvinceNotAttackableException::class);

        $this->move($scenario, 'province');
    }

    public function testRejectsMovingOutsideOfAConflict(): void
    {
        $scenario = $this->scenarioWithConflict(conflict: false);

        $this->expectException(NoConflictException::class);

        $this->move($scenario, 'other');
    }
}
