<?php

declare(strict_types=1);

namespace App\Tests\State\Model;

use App\Entity\Player;
use App\State\Model\GameState;
use PHPUnit\Framework\TestCase;

class GameStateTest extends TestCase
{
    public function testOtherPlayersFollowThePlayerOrderAndWrapAround(): void
    {
        [$john, $jane, $jack] = [new Player('john'), new Player('jane'), new Player('jack')];
        $state = new GameState()->setPlayerOrder($john, $jane, $jack);

        self::assertSame(['jack', 'john'], $state->getPlayerIdsAfter($jane));
        self::assertSame('john', $state->getNextPlayerId($jack));
    }

    public function testAPlayerOutsideThePlayerOrderHasNoNextPlayer(): void
    {
        $state = new GameState()->setPlayerOrder(new Player('john'), new Player('jane'));

        $this->expectException(\LogicException::class);

        $state->getNextPlayerId(new Player('jack'));
    }
}
