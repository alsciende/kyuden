<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;

/**
 * The defending player declares the ready characters defending the attacked province (3.2.1), possibly none.
 */
#[AppAction]
final readonly class DeclareDefenders implements PlayerActionInterface
{
    /**
     * @param list<string> $defenderIds
     */
    public function __construct(
        private string $gameId,
        private string $playerId,
        private array $defenderIds,
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

    /**
     * @return list<string>
     */
    public function getDefenderIds(): array
    {
        return $this->defenderIds;
    }
}
