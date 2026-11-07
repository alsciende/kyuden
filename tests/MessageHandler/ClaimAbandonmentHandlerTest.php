<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\Game;
use App\Enum\VictoryCondition;
use App\Exception\Rules\GameNotAbandonedException;
use App\Message\ClaimAbandonment;
use App\MessageHandler\ClaimAbandonmentHandler;
use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use App\Tests\Builder\Scenario;

class ClaimAbandonmentHandlerTest extends AbstractKernelTestCase
{
    private function scenarioWaitingForJane(): Scenario
    {
        return $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10))
            ->activePlayer('jane')
            ->persist();
    }

    public function testThePlayerTheGameWaitsForHasAbandonedIt(): void
    {
        $scenario = $this->scenarioWaitingForJane();

        self::service(ClaimAbandonmentHandler::class)(new ClaimAbandonment($scenario->game()->getId(), $scenario->player('john')->getId()));

        self::assertTrue($this->playerStateManager->getState($scenario->player('jane'))->isAbandoned());
        self::assertFalse($this->playerStateManager->getState($scenario->player('john'))->isAbandoned());
        self::assertSame(['jane has abandoned the game'], $this->logLines($scenario->game()));
    }

    public function testThePlayerWhoClaimsWinsAtOnce(): void
    {
        $scenario = $this->scenarioWaitingForJane();

        $this->messageBus->dispatch(new ClaimAbandonment($scenario->game()->getId(), $scenario->player('john')->getId()));

        $game = $scenario->game();
        self::assertSame(Game::STATE_ENDED, $game->getCurrentPlace());
        $state = self::service(GameStateManager::class)->getState($game);
        self::assertSame($scenario->player('john')->getId(), $state->winnerId);
        self::assertSame(VictoryCondition::Abandonment, $state->victoryCondition);
        self::assertSame(['jane has abandoned the game', 'The game ends', 'john wins the game: abandonment'], $this->logLines($game));
    }

    public function testAPlayerAbandonedCannotWinWithTheirHonor(): void
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(25))
            ->activePlayer('jane')
            ->persist();

        $this->messageBus->dispatch(new ClaimAbandonment($scenario->game()->getId(), $scenario->player('john')->getId()));

        self::assertSame($scenario->player('john')->getId(), self::service(GameStateManager::class)->getState($scenario->game())->winnerId);
    }

    public function testThePlayerTheGameWaitsForCannotClaim(): void
    {
        $scenario = $this->scenarioWaitingForJane();

        $this->expectException(GameNotAbandonedException::class);

        self::service(ClaimAbandonmentHandler::class)(new ClaimAbandonment($scenario->game()->getId(), $scenario->player('jane')->getId()));
    }

    public function testNobodyClaimsWhenTheGameWaitsForBothPlayers(): void
    {
        $scenario = $this->scenario()
            ->atPlace('honor_bid')
            ->withPlayer('john')
            ->withPlayer('jane')
            ->persist();

        $this->expectException(GameNotAbandonedException::class);

        self::service(ClaimAbandonmentHandler::class)(new ClaimAbandonment($scenario->game()->getId(), $scenario->player('john')->getId()));
    }
}
