<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;

/**
 * The defending player declares the strength of an attacked province, with every modifier applied, when it differs
 * from its printed strength (3.2.5). To be sent before the skill totals, which determine the result.
 */
#[AppAction]
final readonly class DeclareProvinceStrength implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        private string $provinceId,
        private int $strength,
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

    public function getStrength(): int
    {
        return $this->strength;
    }
}
