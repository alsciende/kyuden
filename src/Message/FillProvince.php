<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;

#[AppAction]
final readonly class FillProvince implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        private string $provinceId,
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

    public function getProvinceId(): string
    {
        return $this->provinceId;
    }
}
