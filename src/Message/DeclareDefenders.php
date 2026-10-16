<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The defending player declares the ready characters defending the attacked province (3.2.1), at least one: declaring
 * no defender is declare_no_defenders.
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
        #[Description('Ready characters of the player declared as defenders, at least one.')]
        #[Assert\Count(min: 1)]
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
