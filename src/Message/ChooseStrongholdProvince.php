<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppActionMessage;

#[AppActionMessage]
final readonly class ChooseStrongholdProvince implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        private string $cardId,
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
