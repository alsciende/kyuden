<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use App\Enum\ConflictType;

/**
 * A player may declare an additional conflict of a type this round: one more conflict opportunity (Hida O-Ushi, Hisu
 * Mori Toride, Shori, Suffer the Consequences, Courteous Scheming).
 */
#[AppAction(special: true)]
final readonly class GrantConflictOpportunity implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Player who may declare the additional conflict.')]
        private string $targetPlayerId,
        #[Description('Type of the additional conflict.')]
        private ConflictType $type,
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

    public function getType(): ConflictType
    {
        return $this->type;
    }
}
