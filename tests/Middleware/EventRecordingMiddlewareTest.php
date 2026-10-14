<?php

declare(strict_types=1);

namespace App\Tests\Middleware;

use App\Entity\Event;
use App\Message\LoseHonor;
use App\Repository\EventRepository;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;

class EventRecordingMiddlewareTest extends AbstractKernelTestCase
{
    public function testEachMessageIsAnEventNumberedByTheVersionOfTheGame(): void
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10))
            ->withPlayer('jane', fn (PlayerBuilder $p): PlayerBuilder => $p->honor(10))
            ->persist();
        $game = $scenario->game();
        $johnId = $scenario->player('john')->getId();

        $this->messageBus->dispatch(new LoseHonor($game->getId(), $johnId, $johnId, 1));
        $this->messageBus->dispatch(new LoseHonor($game->getId(), $johnId, $scenario->player('jane')->getId(), 2));

        self::assertSame(2, $game->getVersion());
        $events = self::service(EventRepository::class)->findBy(['game' => $game], ['version' => 'ASC']);
        self::assertSame([1, 2], array_map(static fn (Event $event): int => $event->getVersion(), $events));
        self::assertSame([LoseHonor::class, LoseHonor::class], array_map(static fn (Event $event): string => $event->getName(), $events));
    }

    public function testCreatingAGameIsItsFirstVersion(): void
    {
        $game = $this->createGame();

        self::assertSame(1, $game?->getVersion());
    }
}
