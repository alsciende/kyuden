<?php

declare(strict_types=1);

namespace App\Task;

use App\Service\Abandonment\AbandonedGamesEnder;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'task.bus')]
final readonly class EndAbandonedGamesHandler
{
    public function __construct(
        private AbandonedGamesEnder $ender,
    ) {
    }

    public function __invoke(EndAbandonedGames $task): void
    {
        $this->ender->endAll();
    }
}
