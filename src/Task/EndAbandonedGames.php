<?php

declare(strict_types=1);

namespace App\Task;

use Symfony\Component\Messenger\Attribute\AsMessage;

/**
 * Ends the games every player has left (AbandonedGamesEnder): sent by the schedule of the application (App\Schedule)
 * every INTERVAL, received by the worker from the transport scheduler_default.
 */
#[AsMessage]
final readonly class EndAbandonedGames implements TaskInterface
{
    public const string INTERVAL = '5 minutes';
}
