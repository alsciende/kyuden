<?php

declare(strict_types=1);

namespace App\Task;

use Symfony\Component\Messenger\Attribute\AsMessage;

/**
 * The house bot plays in a game until it has nothing to do (HouseBot::play()): sent once a message has changed a game it
 * plays (HouseBotMiddleware), it may find nothing to do.
 */
#[AsMessage]
final readonly class PlayHouseBotTurn implements TaskInterface
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
