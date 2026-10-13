<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Exception\Rules\NotActivePlayerException;
use App\Message\EndOpportunity;
use App\MessageHandler\EndOpportunityHandler;
use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class EndOpportunityHandlerTest extends AbstractKernelTestCase
{
    private function end(Scenario $scenario, string $player): void
    {
        self::service(EndOpportunityHandler::class)(new EndOpportunity($scenario->game()->getId(), $scenario->player($player)->getId()));
    }

    private function isActivePlayer(Scenario $scenario, string $player): bool
    {
        return self::service(GameStateManager::class)->getState($scenario->game())->isActivePlayer($scenario->player($player));
    }

    public function testTheNextPlayerWhoHasNotPassedGetsTheOpportunityToAct(): void
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john')
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->passed())
            ->withPlayer('jack')
            ->activePlayer('john')
            ->persist();

        $this->end($scenario, 'john');

        self::assertTrue($this->isActivePlayer($scenario, 'jack'));
    }

    public function testThePlayerKeepsTheOpportunityToActWhenEveryOtherPlayerHasPassed(): void
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john')
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->passed())
            ->activePlayer('john')
            ->persist();

        $this->end($scenario, 'john');

        self::assertTrue($this->isActivePlayer($scenario, 'john'));
    }

    public function testRejectsAPlayerWhoIsNotActive(): void
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john')
            ->withPlayer('jane')
            ->activePlayer('john')
            ->persist();

        $this->expectException(NotActivePlayerException::class);

        $this->end($scenario, 'jane');
    }

    public function testPlayersActingSimultaneouslyKeepTheirOpportunityToAct(): void
    {
        $scenario = $this->scenario()
            ->atPlace('reveal_honor_dials')
            ->withPlayer('john')
            ->withPlayer('jane')
            ->activePlayers('john', 'jane')
            ->persist();

        $this->end($scenario, 'john');

        self::assertTrue($this->isActivePlayer($scenario, 'john'));
        self::assertTrue($this->isActivePlayer($scenario, 'jane'));
    }

    public function testThePlayersWhoHadPassedGetOpportunitiesToActAgainInAnActionWindow(): void
    {
        $scenario = $this->scenario()
            ->atPlace('draw_phase_action_window')
            ->withPlayer('john')
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->passed())
            ->activePlayer('john')
            ->persist();

        $this->end($scenario, 'john');

        self::assertTrue($this->isActivePlayer($scenario, 'jane'));
        self::assertFalse($this->playerStateManager->getState($scenario->player('jane'))->isPassed());
    }
}
