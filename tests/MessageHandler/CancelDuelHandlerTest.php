<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Exception\Rules\NoDuelException;
use App\Message\CancelDuel;
use App\MessageHandler\CancelDuelHandler;
use App\State\Manager\GameStateManager;
use App\State\Manager\PlayerStateManager;
use App\State\Model\GameState;
use App\State\Model\PlayerState;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class CancelDuelHandlerTest extends AbstractKernelTestCase
{
    private function scenarioInDuel(bool $duel = true, bool $revealed = false): Scenario
    {
        $builder = $this->scenario()
            ->atPlace('conflict_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->inPlay(['spear' => '01-master-of-the-spear']));

        return ($duel ? $builder->duel('john', ['toturi'], ['spear'], revealed: $revealed) : $builder)->persist();
    }

    private function cancel(Scenario $scenario): void
    {
        // the challenged player sends it (Stay Your Hand); any player can
        self::service(CancelDuelHandler::class)(new CancelDuel($scenario->game()->getId(), $scenario->player('jane')->getId()));
    }

    public function testTheDuelEndsWithoutResultAndTheSecretBidsAreForgotten(): void
    {
        $scenario = $this->scenarioInDuel();
        $players = self::service(PlayerStateManager::class);
        $players->withState($scenario->player('john'), fn (PlayerState $state): PlayerState => $state->setPendingHonorBid(3));

        $this->cancel($scenario);

        self::assertNull(self::service(GameStateManager::class)->getState($scenario->game())->duel);
        self::assertNull($players->getState($scenario->player('john'))->getPendingHonorBid());
        self::assertSame(['The duel is cancelled'], $this->logLines($scenario->game(), $scenario->player('jane')));
    }

    public function testCancelsADuelWhoseResultIsKnown(): void
    {
        $scenario = $this->scenarioInDuel(revealed: true);
        self::service(GameStateManager::class)->withState($scenario->game(), function (GameState $state): GameState {
            $state->currentDuel()->resolved = true;

            return $state;
        });

        $this->cancel($scenario);

        self::assertNull(self::service(GameStateManager::class)->getState($scenario->game())->duel);
    }

    public function testRejectsCancellingWhileNoDuelIsResolved(): void
    {
        $scenario = $this->scenarioInDuel(duel: false);

        $this->expectException(NoDuelException::class);

        $this->cancel($scenario);
    }
}
