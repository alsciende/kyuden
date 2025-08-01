<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;

#[AppAction]
class RevealDynastyCard implements PlayerActionInterface
{
    public function __construct(
        private readonly string $gameId,
        private readonly string $playerId,
        private readonly string $cardId,
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

    public function getCardId(): string
    {
        return $this->cardId;
    }
}
