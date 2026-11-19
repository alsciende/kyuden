<?php

declare(strict_types=1);

namespace App\Tests\Middleware;

use App\Entity\ActionRecord;
use App\Entity\GameLogEntry;
use App\Message\LoseHonor;
use App\Message\PassOpportunity;
use App\Repository\ActionRecordRepository;
use App\Repository\GameLogEntryRepository;
use App\Tests\AbstractKernelTestCase;
use App\Tests\Builder\PlayerBuilder;

class ActionRecordingMiddlewareTest extends AbstractKernelTestCase
{
    public function testEachMessageIsARecordNumberedByTheVersionOfTheGame(): void
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
        $records = self::service(ActionRecordRepository::class)->findBy(['game' => $game], ['version' => 'ASC']);
        self::assertSame([1, 2], array_map(static fn (ActionRecord $record): int => $record->getVersion(), $records));
        self::assertSame([LoseHonor::class, LoseHonor::class], array_map(static fn (ActionRecord $record): string => $record->getName(), $records));
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
