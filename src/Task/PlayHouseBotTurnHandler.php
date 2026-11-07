<?php

declare(strict_types=1);

namespace App\Task;

use App\Service\Bot\HouseBot;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'task.bus')]
final readonly class PlayHouseBotTurnHandler
{
    public function __construct(
        private HouseBot $houseBot,
    ) {
    }

    public function __invoke(PlayHouseBotTurn $task): void
    {
        $this->houseBot->play($task->getGameId());
    }
}
