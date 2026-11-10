<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\CardTypes\ProvinceCard;
use App\Enum\Orientation;
use App\Exception\Rules\NoConflictException;
use App\Exception\Rules\ProvinceNotAttackableException;
use App\Message\AddAttackedProvince;
use App\MessageHandler\AddAttackedProvinceHandler;
use App\State\Manager\GameStateManager;
use App\State\Model\ConflictState;
use App\State\Model\GameState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class AddAttackedProvinceHandlerTest extends AbstractKernelTestCase
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
                ->province('01-night-raid', as: 'broken', orientation: Orientation::UpsideDown));
        if ($conflict) {
            $builder->conflict('john', 'province', attackers: ['toturi']);
        }
        if ($change !== null) {
            $builder->withGameState($change);
        }

        return $builder->persist();
    }

    private function add(Scenario $scenario, string $province): void
    {
        // the attacking player sends it (A War on Two Fronts): any player can
        self::service(AddAttackedProvinceHandler::class)(new AddAttackedProvince(
            $scenario->game()->getId(),
            $scenario->player('john')->getId(),
            $scenario->card($province)->getId(),
        ));
    }

    private function conflict(Scenario $scenario): ConflictState
    {
        return self::service(GameStateManager::class)->getState($scenario->game())->currentConflict();
    }

    public function testTheConflictIsAlsoAtAFacedownProvinceThatAPlayerReveals(): void
    {
        $scenario = $this->scenarioWithConflict();

        $this->add($scenario, 'other');

        $conflict = $this->conflict($scenario);
        // the contested ring stays where it was
        self::assertSame($scenario->card('province')->getId(), $conflict->provinceId);
        self::assertSame([$scenario->card('province')->getId(), $scenario->card('other')->getId()], $conflict->attackedProvinceIds);
        // revealed by RevealProvince, so that the players see it
        self::assertSame(ProvinceCard::STATE_FACEDOWN, $scenario->card('other')->getCurrentPlace());
    }

    public function testOnceRecordedTheNewProvinceHasItsStrength(): void
    {
        $scenario = $this->scenarioWithConflict(change: function (GameState $state, Scenario $scenario): void {
            $state->currentConflict()->provinceStrengths = [$scenario->card('province')->getId() => 5];
        });

        $this->add($scenario, 'other');

        self::assertSame([
            $scenario->card('province')->getId() => 5,
            $scenario->card('other')->getId() => 4,
        ], $this->conflict($scenario)->provinceStrengths);
    }

    public function testRejectsABrokenProvince(): void
    {
        $scenario = $this->scenarioWithConflict();

        $this->expectException(ProvinceNotAttackableException::class);

        $this->add($scenario, 'broken');
    }

    public function testRejectsAProvinceOfTheAttackingPlayer(): void
    {
        $scenario = $this->scenarioWithConflict();

        $this->expectException(ProvinceNotAttackableException::class);

        $this->add($scenario, 'johns-province');
    }

    public function testRejectsAnAttackedProvince(): void
    {
        $scenario = $this->scenarioWithConflict();

        $this->expectException(ProvinceNotAttackableException::class);

        $this->add($scenario, 'province');
    }

    public function testRejectsAddingOutsideOfAConflict(): void
    {
        $scenario = $this->scenarioWithConflict(conflict: false);

        $this->expectException(NoConflictException::class);

        $this->add($scenario, 'other');
    }
}
