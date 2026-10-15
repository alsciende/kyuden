<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Enum\DuelType;
use App\Exception\Rules\DuelInProgressException;
use App\Message\BidHonor;
use App\Message\DeclareDuelStatistic;
use App\Message\EndDuel;
use App\Message\EndOpportunity;
use App\Message\InitiateDuel;
use App\Message\PassOpportunity;
use App\MessageHandler\PassOpportunityHandler;
use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

/**
 * A duel initiated in an action window, resolved through the message bus, then the action window goes on.
 */
class DuelFlowTest extends AbstractKernelTestCase
{
    private function scenarioInActionWindow(): Scenario
    {
        return $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10)->honorBid(1)->inPlay(['toturi' => '01-akodo-toturi']))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10)->honorBid(5)->inPlay(['spear' => '01-master-of-the-spear']))
            ->activePlayer('john')
            ->persist();
    }

    private function id(Scenario $scenario, string $player): string
    {
        return $scenario->player($player)->getId();
    }

    public function testADuelIsResolvedThenTheActionWindowGoesOn(): void
    {
        $scenario = $this->scenarioInActionWindow();
        $gameId = $scenario->game()->getId();

        $this->messageBus->dispatch(new InitiateDuel($gameId, $this->id($scenario, 'john'), DuelType::Military, [$scenario->card('toturi')->getId()], [$scenario->card('spear')->getId()]));
        $this->messageBus->dispatch(new BidHonor($gameId, $this->id($scenario, 'john'), 4));
        $this->messageBus->dispatch(new BidHonor($gameId, $this->id($scenario, 'jane'), 2));
        // john: 3 + 4, jane: 6 + 2
        $this->messageBus->dispatch(new DeclareDuelStatistic($gameId, $this->id($scenario, 'john'), 3));
        $this->messageBus->dispatch(new DeclareDuelStatistic($gameId, $this->id($scenario, 'jane'), 6));

        $duel = self::service(GameStateManager::class)->getState($scenario->game())->currentDuel();
        self::assertSame($this->id($scenario, 'jane'), $duel->winnerId);
        self::assertSame(8, $this->playerStateManager->getState($scenario->player('john'))->getHonor());
        self::assertSame(12, $this->playerStateManager->getState($scenario->player('jane'))->getHonor());

        $this->messageBus->dispatch(new EndDuel($gameId, $this->id($scenario, 'john')));
        $this->messageBus->dispatch(new EndOpportunity($gameId, $this->id($scenario, 'john')));

        self::assertNull(self::service(GameStateManager::class)->getState($scenario->game())->duel);
        self::assertTrue(self::service(GameStateManager::class)->getState($scenario->game())->isActivePlayer($scenario->player('jane')));
        self::assertSame('draw_phase_action_window', $scenario->game()->getCurrentPlace());
        self::assertSame([
            'john initiates a military duel: Akodo Toturi against Master of the Spear',
            'john bids ? honor',
            'jane bids 2 honor',
            'john reveals an honor bid of 4',
            'jane reveals an honor bid of 2',
            'john declares a statistic of 3',
            'jane declares a statistic of 6',
            'john gives 2 honor to jane',
            'jane wins the duel',
            'The duel ends',
        ], $this->logLines($scenario->game(), $scenario->player('jane')));
    }

    public function testThePlayersCannotPassDuringADuel(): void
    {
        $scenario = $this->scenarioInActionWindow();
        $gameId = $scenario->game()->getId();
        $this->messageBus->dispatch(new InitiateDuel($gameId, $this->id($scenario, 'john'), DuelType::Military, [$scenario->card('toturi')->getId()], [$scenario->card('spear')->getId()]));

        $this->expectException(DuelInProgressException::class);

        self::service(PassOpportunityHandler::class)(new PassOpportunity($gameId, $this->id($scenario, 'john')));
    }
}
