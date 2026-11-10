<?php

declare(strict_types=1);

namespace App\Task;

use Symfony\Component\Messenger\Attribute\AsMessage;

/**
 * Deletes the sessions of the accounts that have expired (ExpiredSessionsDeleter): sent by the schedule of the
 * application (App\Schedule) every INTERVAL, received by the worker from the transport scheduler_default.
 */
#[AsMessage]
final readonly class DeleteExpiredSessions implements TaskInterface
{
    public const string INTERVAL = '1 hour';
}
