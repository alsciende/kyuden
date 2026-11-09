<?php

declare(strict_types=1);

namespace App\Tests\Service\Replay;

use App\Entity\Game;
use App\Entity\Player;
use App\Security\AccountDeleter;
use App\Service\Replay\GameReplayer;
use App\Service\Replay\StaleReplaysDeleter;
use App\Tests\AbstractKernelTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;

class StaleReplaysDeleterTest extends AbstractKernelTestCase
{
    use ClockSensitiveTrait;
    use PlaysEndedGame;

    private ClockInterface $clock;

    /**
     * A replay john follows, read by him now.
     */
    private function replayReadNow(Game $game): Game
    {
        $john = array_find($game->getPlayers()->getValues(), fn (Player $player): bool => $player->getUserId() === 'john') ?? self::fail('john does not play.');
        $replayer = self::service(GameReplayer::class);
        $replay = $replayer->start($game, $john);
        $replayer->proceed($replay);
        $copy = $replayer->getReplayGame($replay) ?? self::fail('No copy.');
        $copy->getPlayer($replay->getReplayViewerId())->setLastSeenAt($this->clock->now());
        self::service(EntityManagerInterface::class)->flush();

        return $copy;
    }

    private function exists(string $gameId): bool
    {
        self::service(EntityManagerInterface::class)->clear();

        return $this->gameRepository->find($gameId) instanceof Game;
    }

    public function testDeletesTheReplaysNotReadForAnHour(): void
    {
        $this->clock = self::mockTime('2026-11-08 12:00:00');
        $game = $this->playEndedGame();
        $copyId = $this->replayReadNow($game)->getId();

        $this->clock->sleep(StaleReplaysDeleter::STALE_AFTER - 60);
        self::assertSame(0, self::service(StaleReplaysDeleter::class)->deleteAll());
        self::assertTrue($this->exists($copyId));

        $this->clock->sleep(120);
        self::assertSame(1, self::service(StaleReplaysDeleter::class)->deleteAll());
        self::assertFalse($this->exists($copyId));
        // the game replayed stays
        self::assertTrue($this->exists($game->getId()));
    }

    public function testTheReplaysOfADeletedAccountAreDeleted(): void
    {
        $this->clock = self::mockTime('2026-11-08 12:00:00');
        $game = $this->playEndedGame();
        $copyId = $this->replayReadNow($game)->getId();
        $john = $game->getPlayers()->get(0)?->getUser() ?? self::fail('john does not play.');

        self::service(AccountDeleter::class)->delete($john);

        self::assertFalse($this->exists($copyId));
        self::assertTrue($this->exists($game->getId()));
    }
}
