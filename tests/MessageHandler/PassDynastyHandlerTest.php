<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Exception\Data\WorkflowException;
use App\Exception\Rules\NotActivePlayerException;
use App\Message\PassDynasty;
use App\MessageHandler\PassDynastyHandler;
use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class PassDynastyHandlerTest extends AbstractKernelTestCase
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

    private function pass(Scenario $scenario, string $player): PassDynasty
    {
        return new PassDynasty($scenario->game()->getId(), $scenario->player($player)->getId());
    }

    public function testTheFirstPlayerToPassGainsOneFate(): void
    {
        $scenario = $this->scenarioAtPlayCardsFromProvinces();

        self::service(PassDynastyHandler::class)($this->pass($scenario, 'john'));

        $state = $this->playerStateManager->getState($scenario->player('john'));
        self::assertSame(3, $state->getFate());
        self::assertTrue($state->isDynastyPassed());
        self::assertTrue(self::service(GameStateManager::class)->getState($scenario->game())->isActivePlayer($scenario->player('jane')));
    }

    public function testTheLastPlayerToPassGainsNoFate(): void
    {
        $scenario = $this->scenarioAtPlayCardsFromProvinces(fn (PlayerBuilder $p): PlayerBuilder => $p->dynastyPassed());

        self::service(PassDynastyHandler::class)($this->pass($scenario, 'john'));

        $state = $this->playerStateManager->getState($scenario->player('john'));
        self::assertSame(2, $state->getFate());
        self::assertTrue($state->isDynastyPassed());
    }

    public function testPlayersWhoHavePassedNoLongerGetTheOpportunityToAct(): void
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->fate(2))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->dynastyPassed())
            ->withPlayer('jack')
            ->activePlayer('john')
            ->persist();

        self::service(PassDynastyHandler::class)($this->pass($scenario, 'john'));

        // jane passed first
        self::assertSame(2, $this->playerStateManager->getState($scenario->player('john'))->getFate());
        self::assertTrue(self::service(GameStateManager::class)->getState($scenario->game())->isActivePlayer($scenario->player('jack')));
    }

    public function testRejectsAPlayerWhoIsNotActive(): void
    {
        $scenario = $this->scenarioAtPlayCardsFromProvinces();

        $this->expectException(NotActivePlayerException::class);

        self::service(PassDynastyHandler::class)($this->pass($scenario, 'jane'));
    }

    public function testRejectsAPassOutsideTheDynastyPhase(): void
    {
        $scenario = $this->scenarioAtPlayCardsFromProvinces(place: 'collect_fate');

        $this->expectException(WorkflowException::class);

        self::service(PassDynastyHandler::class)($this->pass($scenario, 'john'));
    }

    public function testTheDynastyPhaseEndsOnceTheLastPlayerHasPassed(): void
    {
        $scenario = $this->scenarioAtPlayCardsFromProvinces(fn (PlayerBuilder $p): PlayerBuilder => $p->dynastyPassed());

        // through the bus, so that automatic transitions happen
        $this->messageBus->dispatch($this->pass($scenario, 'john'));

        self::assertSame('dynasty_phase_ends', $scenario->game()->getCurrentPlace());
    }
}
