<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Exception\Rules\NotInOpportunitiesStepException;
use App\Exception\Rules\NotActivePlayerException;
use App\Message\PassOpportunity;
use App\MessageHandler\PassOpportunityHandler;
use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class PassOpportunityHandlerTest extends AbstractKernelTestCase
{
    /**
     * @param \Closure(PlayerBuilder): mixed|null $jane
     */
    private function scenarioAtPlayCardsFromProvinces(?\Closure $jane = null, string $place = 'play_cards_from_provinces'): Scenario
    {
        return $this->scenario()
            ->atPlace($place)
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->fate(2))
            ->withPlayer('jane', $jane)
            ->activePlayer('john')
            ->persist();
    }

    private function pass(Scenario $scenario, string $player): PassOpportunity
    {
        return new PassOpportunity($scenario->game()->getId(), $scenario->player($player)->getId());
    }

    public function testTheFirstPlayerToPassGainsOneFate(): void
    {
        $scenario = $this->scenarioAtPlayCardsFromProvinces();

        self::service(PassOpportunityHandler::class)($this->pass($scenario, 'john'));

        $state = $this->playerStateManager->getState($scenario->player('john'));
        self::assertSame(3, $state->getFate());
        self::assertTrue($state->isPassed());
        self::assertTrue(self::service(GameStateManager::class)->getState($scenario->game())->isActivePlayer($scenario->player('jane')));
    }

    public function testTheLastPlayerToPassGainsNoFate(): void
    {
        $scenario = $this->scenarioAtPlayCardsFromProvinces(fn (PlayerBuilder $p): PlayerBuilder => $p->passed());

        self::service(PassOpportunityHandler::class)($this->pass($scenario, 'john'));

        $state = $this->playerStateManager->getState($scenario->player('john'));
        self::assertSame(2, $state->getFate());
        self::assertTrue($state->isPassed());
    }

    public function testPlayersWhoHavePassedNoLongerGetTheOpportunityToAct(): void
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->fate(2))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->passed())
            ->withPlayer('jack')
            ->activePlayer('john')
            ->persist();

        self::service(PassOpportunityHandler::class)($this->pass($scenario, 'john'));

        // jane passed first
        self::assertSame(2, $this->playerStateManager->getState($scenario->player('john'))->getFate());
        self::assertTrue(self::service(GameStateManager::class)->getState($scenario->game())->isActivePlayer($scenario->player('jack')));
    }

    public function testRejectsAPlayerWhoIsNotActive(): void
    {
        $scenario = $this->scenarioAtPlayCardsFromProvinces();

        $this->expectException(NotActivePlayerException::class);

        self::service(PassOpportunityHandler::class)($this->pass($scenario, 'jane'));
    }

    public function testRejectsAPassInAStepWithoutOpportunities(): void
    {
        $scenario = $this->scenarioAtPlayCardsFromProvinces(place: 'collect_fate');

        $this->expectException(NotInOpportunitiesStepException::class);

        self::service(PassOpportunityHandler::class)($this->pass($scenario, 'john'));
    }

    public function testPlayersBidHonorOnceTheLastPlayerHasPassed(): void
    {
        $scenario = $this->scenarioAtPlayCardsFromProvinces(fn (PlayerBuilder $p): PlayerBuilder => $p->passed());

        // through the bus, so that automatic transitions happen
        $this->messageBus->dispatch($this->pass($scenario, 'john'));

        self::assertSame('honor_bid', $scenario->game()->getCurrentPlace());
    }

    public function testPlayersActingSimultaneouslyKeepActingWhenAPlayerPasses(): void
    {
        $scenario = $this->scenario()
            ->atPlace('reveal_honor_dials')
            ->withPlayer('john')
            ->withPlayer('jane')
            ->withPlayer('jack')
            ->activePlayers('john', 'jane', 'jack')
            ->persist();

        self::service(PassOpportunityHandler::class)($this->pass($scenario, 'jane'));

        $state = self::service(GameStateManager::class)->getState($scenario->game());
        self::assertSame([$scenario->player('john')->getId(), $scenario->player('jack')->getId()], $state->activePlayerIds);
    }

    public function testThePlayersTransferHonorOnceTheLastPlayerHasPassedAfterTheHonorDialsAreRevealed(): void
    {
        $scenario = $this->scenario()
            ->atPlace('reveal_honor_dials')
            ->withPlayer('john')
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->passed())
            ->activePlayers('john')
            ->persist();

        // through the bus, so that automatic transitions happen
        $this->messageBus->dispatch($this->pass($scenario, 'john'));

        self::assertSame('transfer_honor', $scenario->game()->getCurrentPlace());
    }
}
