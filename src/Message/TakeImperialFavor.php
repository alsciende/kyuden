<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use App\Enum\ConflictType;

/**
 * A player claims the Imperial Favor by a card effect (Fawning Diplomat), from its unclaimed state or from a player,
 * and sets it to either side; the claim of the glory count is claim_imperial_favor.
 */
#[AppAction(special: true)]
final readonly class TakeImperialFavor implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Player who claims the Imperial Favor.')]
        private string $targetPlayerId,
        #[Description('Side the Imperial Favor is set to.')]
        private ConflictType $side,
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

    public function getTargetPlayerId(): string
    {
        return $this->targetPlayerId;
    }

    public function getSide(): ConflictType
    {
        return $this->side;
    }
}
