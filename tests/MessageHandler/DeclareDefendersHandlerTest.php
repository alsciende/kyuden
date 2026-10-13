<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Enum\Orientation;
use App\Exception\Rules\CannotParticipateException;
use App\Exception\Rules\NotActivePlayerException;
use App\Exception\Rules\NotOwnedCardException;
use App\Message\DeclareDefenders;
use App\MessageHandler\DeclareDefendersHandler;
use App\State\Manager\GameStateManager;
use App\State\Model\GameState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class DeclareDefendersHandlerTest extends AbstractKernelTestCase
{
    private function scenarioAtDeclareDefenders(): Scenario
    {
        return $this->scenario()
            ->atPlace('declare_defenders')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->inPlay(['spear' => '01-master-of-the-spear'])
                ->inPlay(['hotaru' => '01-doji-hotaru'], bowed: true)
                ->inPlay(['oathkeeper' => '01-vengeful-oathkeeper'])
                ->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceUp))
            ->conflict('john', 'province', attackers: ['toturi'])
            ->withGameState(function (GameState $state, Scenario $scenario): void {
                $state->currentConflict()->evadedIds = [$scenario->card('oathkeeper')->getId()];
            })
            ->activePlayer('jane')
            ->persist();
    }

    /**
     * @param list<string> $defenders
     */
    private function declare(Scenario $scenario, array $defenders, string $player = 'jane'): void
    {
        self::service(DeclareDefendersHandler::class)(new DeclareDefenders(
            $scenario->game()->getId(),
            $scenario->player($player)->getId(),
            array_map(fn (string $alias): string => $scenario->card($alias)->getId(), $defenders),
        ));
    }

    public function testTheDefendersAreDeclared(): void
    {
        $scenario = $this->scenarioAtDeclareDefenders();

        $this->declare($scenario, ['spear']);

        $state = self::service(GameStateManager::class)->getState($scenario->game());
        self::assertSame([$scenario->card('spear')->getId()], $state->currentConflict()->defenderIds);
        self::assertSame('conflict_action_window', $scenario->game()->getCurrentPlace());
        self::assertSame([$scenario->player('jane')->getId()], $state->activePlayerIds);
    }

    public function testThereCanBeNoDefenders(): void
    {
        $scenario = $this->scenarioAtDeclareDefenders();

        $this->declare($scenario, []);

        self::assertSame([], self::service(GameStateManager::class)->getState($scenario->game())->currentConflict()->defenderIds);
    }

    public function testRejectsABowedDefender(): void
    {
        $scenario = $this->scenarioAtDeclareDefenders();

        $this->expectException(CannotParticipateException::class);

        $this->declare($scenario, ['hotaru']);
    }

    public function testRejectsADefenderEvadedByCovert(): void
    {
        $scenario = $this->scenarioAtDeclareDefenders();

        $this->expectException(CannotParticipateException::class);

        $this->declare($scenario, ['oathkeeper']);
    }

    public function testRejectsACharacterOfTheAttackingPlayer(): void
    {
        $scenario = $this->scenarioAtDeclareDefenders();

        $this->expectException(NotOwnedCardException::class);

        $this->declare($scenario, ['toturi']);
    }

    public function testRejectsTheAttackingPlayer(): void
    {
        $scenario = $this->scenarioAtDeclareDefenders();

        $this->expectException(NotActivePlayerException::class);

        $this->declare($scenario, [], 'john');
    }
}
