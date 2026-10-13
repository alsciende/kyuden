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

    public function testEveryPlayerCanBeActive(): void
    {
        [$john, $jane] = [new Player('john'), new Player('jane')];
        $state = new GameState()->setPlayerOrder($john, $jane)->activateAllPlayers();

        self::assertTrue($state->isActivePlayer($john));
        self::assertTrue($state->isActivePlayer($jane));
        self::assertSame(2, $state->countActivePlayers());
    }

    public function testADeactivatedPlayerIsNoLongerActive(): void
    {
        [$john, $jane] = [new Player('john'), new Player('jane')];
        $state = new GameState()->setPlayerOrder($john, $jane)->activateAllPlayers()->deactivatePlayer($john);

        self::assertFalse($state->isActivePlayer($john));
        self::assertTrue($state->isActivePlayer($jane));
        self::assertSame(1, $state->countActivePlayers());

        self::assertSame(0, $state->deactivatePlayer($jane)->countActivePlayers());
    }
}
