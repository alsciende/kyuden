<?php

declare(strict_types=1);

namespace App\Tests;

use App\Schedule;
use App\Task\EndAbandonedGames;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\RecurringMessage;

class ScheduleTest extends AbstractKernelTestCase
{
    public function testTheAbandonedGamesAreEndedEveryFewMinutes(): void
    {
        $recurring = self::service(Schedule::class)->getSchedule()->getRecurringMessages();

        self::assertCount(1, $recurring);
        $message = array_first($recurring);
        self::assertInstanceOf(RecurringMessage::class, $message);
        $context = new MessageContext('default', $message->getId(), $message->getTrigger(), new \DateTimeImmutable());
        self::assertEquals([new EndAbandonedGames()], iterator_to_array($message->getMessages($context)));
    }
}
