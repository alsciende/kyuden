<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;

/**
 * The player says how much fate they collect: card effects may modify the fate value of their stronghold.
 */
#[AppAction]
final readonly class CollectFate implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        private int $amount,
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

    public function getAmount(): int
    {
        return $this->amount;
    }
}
