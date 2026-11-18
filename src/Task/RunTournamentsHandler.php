<?php

declare(strict_types=1);

namespace App\Task;

use App\Service\Tournament\TournamentRunner;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'task.bus')]
final readonly class RunTournamentsHandler
{
    public function __construct(
        private TournamentRunner $tournamentRunner,
    ) {
    }

    public function __invoke(RunTournaments $task): void
    {
        $this->tournamentRunner->runDue();
    }
}
