<?php

declare(strict_types=1);

namespace App\Tests\Middleware;

use App\Entity\Event;
use App\Entity\GameLogEntry;
use App\Message\LoseHonor;
use App\Message\PassOpportunity;
use App\Repository\EventRepository;
use App\Repository\GameLogEntryRepository;
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

    public function testTheLogEntriesOfTheAutomaticTransitionsBelongToTheVersionOfTheMessageThatLedToThem(): void
    {
        $scenario = $this->scenario()
            ->atPlace('play_cards_from_provinces')
            ->withPlayer('john', fn (PlayerBuilder $p): PlayerBuilder => $p->passed())
            ->withPlayer('jane')
            ->activePlayer('jane')
            ->persist();
        $game = $scenario->game();

        $this->messageBus->dispatch(new PassOpportunity($game->getId(), $scenario->player('jane')->getId()));

        $entries = self::service(GameLogEntryRepository::class)->findSince($game);
        self::assertSame('The draw phase begins', $entries[3]->getTemplate());
        self::assertSame([1], array_values(array_unique(array_map(static fn (GameLogEntry $entry): int => $entry->getVersion(), $entries))));
    }

    public function testCreatingAGameIsItsFirstVersion(): void
    {
        $game = $this->createGame();

        self::assertSame(1, $game?->getVersion());
    }
}
