<?php

declare(strict_types=1);

namespace App;

use App\Task\DeleteExpiredSessions;
use App\Task\DeleteStaleReplays;
use App\Task\EndAbandonedGames;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule as SymfonySchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * The recurring tasks of the worker (src/Task), which consumes the transport of this schedule (scheduler_default) with
 * async, dispatching both on task.bus (messenger:consume async scheduler_default --bus=task.bus). Stateful: the worker
 * starting again (every hour) neither runs a task again nor waits a whole interval; a run missed while it was stopped is
 * run once.
 */
#[AsSchedule]
final readonly class Schedule implements ScheduleProviderInterface
{
    public function __construct(
        private CacheInterface $cache,
    ) {
    }

    #[\Override]
    public function getSchedule(): SymfonySchedule
    {
        return new SymfonySchedule()
            ->stateful($this->cache)
            ->processOnlyLastMissedRun(true)
            ->add(RecurringMessage::every(EndAbandonedGames::INTERVAL, new EndAbandonedGames()))
            ->add(RecurringMessage::every(DeleteStaleReplays::INTERVAL, new DeleteStaleReplays()))
            ->add(RecurringMessage::every(DeleteExpiredSessions::INTERVAL, new DeleteExpiredSessions()));
    }
}
