<?php

declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\Game;
use App\Enum\VictoryCondition;
use App\Exception\Rules\GameNotStartedException;
use App\Message\EndAbandonedGame;
use App\MessageHandler\EndAbandonedGameHandler;
use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;

class EndAbandonedGameHandlerTest extends AbstractKernelTestCase
{
    public function testEveryPlayerHasAbandonedTheGameAndNobodyWins(): void
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john')
            ->withPlayer('jane')
            ->activePlayer('jane')
            ->persist();

        $this->messageBus->dispatch(new EndAbandonedGame($scenario->game()->getId()));

        $game = $scenario->game();
        self::assertSame(Game::STATE_ENDED, $game->getCurrentPlace());
        self::assertTrue($this->playerStateManager->getState($scenario->player('john'))->isAbandoned());
        self::assertTrue($this->playerStateManager->getState($scenario->player('jane'))->isAbandoned());
        $state = self::service(GameStateManager::class)->getState($game);
        self::assertNull($state->winnerId);
        self::assertSame(VictoryCondition::Abandonment, $state->victoryCondition);
        self::assertSame([
            'john has abandoned the game',
            'jane has abandoned the game',
            'The game ends',
            'Nobody wins the game: abandonment',
        ], $this->logLines($game));
    }

    public function testAGameNotStartedIsNotEnded(): void
    {
        $scenario = $this->scenario()
            ->atPlace('join_game')
            ->withPlayer('john')
            ->persist();

        $this->expectException(GameNotStartedException::class);
        self::service(EndAbandonedGameHandler::class)(new EndAbandonedGame($scenario->game()->getId()));
    }
}
