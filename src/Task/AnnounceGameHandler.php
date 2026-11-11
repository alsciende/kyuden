<?php

declare(strict_types=1);

namespace App\Task;

use App\Service\Discord\GameAnnouncer;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'task.bus')]
final readonly class AnnounceGameHandler
{
    public function __construct(
        private GameAnnouncer $gameAnnouncer,
    ) {
    }

    public function __invoke(AnnounceGame $task): void
    {
        $this->gameAnnouncer->announce($task->getGameId());
    }
}
