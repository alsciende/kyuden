<?php

declare(strict_types=1);

namespace App\Tests\Workflow\Game;

use App\Enum\Orientation;
use App\State\Model\GameState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;
use App\Workflow\Game\UnopposedAppliedTransitionListener;
use Symfony\Component\Workflow\Event\TransitionEvent;
use Symfony\Component\Workflow\Marking;

class UnopposedAppliedTransitionListenerTest extends AbstractKernelTestCase
{
    /**
     * @param list<string> $defenders
     */
    private function janeHonorAfter(string $winner, array $defenders): ?int
    {
        $scenario = $this->scenario()
            ->atPlace('apply_unopposed')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10)->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p
                ->honor(10)
                ->inPlay(['spear' => '01-master-of-the-spear'])
                ->province('01-entrenched-position', as: 'province', orientation: Orientation::FaceUp))
            ->conflict('john', 'province', attackers: ['toturi'], defenders: $defenders)
            ->withGameState(function (GameState $state, Scenario $scenario) use ($winner): void {
                $state->currentConflict()->winnerId = $scenario->player($winner)->getId();
            })
            ->build();

        self::service(UnopposedAppliedTransitionListener::class)(new TransitionEvent($scenario->game(), new Marking()));

        return $this->playerStateManager->getState($scenario->player('jane'))->getHonor();
    }

    public function testTheDefendingPlayerLosesOneHonorWhenTheAttackingPlayerWinsUnopposed(): void
    {
        self::assertSame(9, $this->janeHonorAfter('john', []));
    }

    public function testNothingHappensWithDefendingCharacters(): void
    {
        self::assertSame(10, $this->janeHonorAfter('john', ['spear']));
    }

    public function testNothingHappensWhenTheDefendingPlayerWins(): void
    {
        self::assertSame(10, $this->janeHonorAfter('jane', []));
    }
}
