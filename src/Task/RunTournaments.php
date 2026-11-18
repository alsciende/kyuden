<?php

declare(strict_types=1);

namespace App\Task;

use Symfony\Component\Messenger\Attribute\AsMessage;

/**
 * Moves the tournaments on (TournamentRunner): locks them at H-1, begins them at H, ends the matches whose players have
 * not joined in time. Sent by the schedule of the application (App\Schedule) every INTERVAL, received by the worker
 * from the transport scheduler_default.
 */
#[AsMessage]
final readonly class RunTournaments implements TaskInterface
{
    public const string INTERVAL = '1 minute';
}
