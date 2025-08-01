<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;

#[AppAction]
final readonly class JoinGame implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        private string $userId,
        private string $deckId,
    ) {
    }

    #[\Override]
    public function getGameId(): string
    {
        return $this->gameId;
    }

    #[\Override]
    public function getPlayerId(): string
    {
        return $this->playerId;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getDeckId(): string
    {
        return $this->deckId;
    }
}
