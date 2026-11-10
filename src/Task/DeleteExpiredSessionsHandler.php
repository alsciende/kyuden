<?php

declare(strict_types=1);

namespace App\Task;

use App\Security\ExpiredSessionsDeleter;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'task.bus')]
final readonly class DeleteExpiredSessionsHandler
{
    public function __construct(
        private ExpiredSessionsDeleter $deleter,
    ) {
    }

    public function __invoke(DeleteExpiredSessions $task): void
    {
        $this->deleter->deleteAll();
    }
}
