<?php

declare(strict_types=1);

namespace App\Task;

use App\Service\Replay\StaleReplaysDeleter;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'task.bus')]
final readonly class DeleteStaleReplaysHandler
{
    public function __construct(
        private StaleReplaysDeleter $deleter,
    ) {
    }

    public function __invoke(DeleteStaleReplays $task): void
    {
        $this->deleter->deleteAll();
    }
}
