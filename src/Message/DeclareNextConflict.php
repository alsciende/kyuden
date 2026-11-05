<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * A player declares the next conflict of the phase, out of the order of the conflict opportunities, with one of their
 * own (Breakthrough, Ivory Kingdoms Unicorn: its type is switched once declared, switch_conflict_type).
 */
#[AppAction(special: true)]
final readonly class DeclareNextConflict implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Player who declares the next conflict.')]
        private string $targetPlayerId,
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
}
