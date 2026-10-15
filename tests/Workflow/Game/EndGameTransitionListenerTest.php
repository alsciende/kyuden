<?php

declare(strict_types=1);

namespace App\Tests\Workflow\Game;

use App\Entity\Game;
use App\Enum\VictoryCondition;
use App\State\Manager\GameStateManager;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;
use Symfony\Component\Workflow\Registry;

class EndGameTransitionListenerTest extends AbstractKernelTestCase
{
    public function testRecordsTheWinnerAndTheVictoryCondition(): void
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(25))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10))
            ->activePlayer('jane')
            ->build();

        self::service(Registry::class)->get($scenario->game())->apply($scenario->game(), 'end_game');

        $state = self::service(GameStateManager::class)->getState($scenario->game());
        self::assertSame(Game::STATE_ENDED, $scenario->game()->getCurrentPlace());
        self::assertSame($scenario->player('john')->getId(), $state->winnerId);
        self::assertSame(VictoryCondition::HonorWin, $state->victoryCondition);
        self::assertSame([], $state->activePlayerIds);
        self::assertSame(['The game ends', 'john wins the game: honor_win'], $this->logLines($scenario->game()));
    }
}
