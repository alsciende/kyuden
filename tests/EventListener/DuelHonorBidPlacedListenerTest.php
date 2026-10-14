<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Event\HonorBidPlacedEvent;
use App\EventListener\DuelHonorBidPlacedListener;
use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class DuelHonorBidPlacedListenerTest extends AbstractKernelTestCase
{
    private function bidPlaced(?int $janeBid): Scenario
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->pendingHonorBid(4)->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => ($janeBid === null ? $p : $p->pendingHonorBid($janeBid))->inPlay(['spear' => '01-master-of-the-spear']))
            ->duel('john', ['toturi'], ['spear'])
            ->build();

        self::service(DuelHonorBidPlacedListener::class)(new HonorBidPlacedEvent($scenario->game(), $scenario->player('john')));

        return $scenario;
    }

    public function testTheHonorDialsAreRevealedOnceBothPlayersHaveBid(): void
    {
        $scenario = $this->bidPlaced(janeBid: 2);

        self::assertFalse(self::service(GameStateManager::class)->getState($scenario->game())->currentDuel()->atBidStep());
        foreach (['john' => 4, 'jane' => 2] as $alias => $bid) {
            $state = $this->playerStateManager->getState($scenario->player($alias));
            self::assertSame($bid, $state->getHonorBid());
            self::assertNull($state->getPendingHonorBid());
        }
    }

    public function testTheBidsStaySecretWhileAPlayerHasNotBid(): void
    {
        $scenario = $this->bidPlaced(janeBid: null);

        self::assertTrue(self::service(GameStateManager::class)->getState($scenario->game())->currentDuel()->atBidStep());
        self::assertSame(4, $this->playerStateManager->getState($scenario->player('john'))->getPendingHonorBid());
    }
}
