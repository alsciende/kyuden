<?php

declare(strict_types=1);

namespace App\Tests;

use App\Schedule;
use App\Task\DeleteStaleReplays;
use App\Task\EndAbandonedGames;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\RecurringMessage;

class ScheduleTest extends AbstractKernelTestCase
{
    public function testTheAbandonedGamesAreEndedAndTheStaleReplaysDeletedEveryFewMinutes(): void
    {
        $recurring = self::service(Schedule::class)->getSchedule()->getRecurringMessages();

        self::assertCount(2, $recurring);
        $messages = [];
        foreach ($recurring as $message) {
            self::assertInstanceOf(RecurringMessage::class, $message);
            $context = new MessageContext('default', $message->getId(), $message->getTrigger(), new \DateTimeImmutable());
            $messages = [...$messages, ...iterator_to_array($message->getMessages($context), false)];
        }
        self::assertEquals([new EndAbandonedGames(), new DeleteStaleReplays()], $messages);
    }
}
