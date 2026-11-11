<?php

declare(strict_types=1);

namespace App\Task;

use Symfony\Component\Messenger\Attribute\AsMessage;

/**
 * Announces on the Discord server a game that has started, with the link to watch it (GameAnnouncer::announce()): sent
 * once the last player has joined (GameUpdatedNotificationMiddleware), when the webhook is set.
 */
#[AsMessage]
final readonly class AnnounceGame implements TaskInterface
{
    public function __construct(
        private string $gameId,
    ) {
    }

    public function getGameId(): string
    {
        return $this->gameId;
    }
}
