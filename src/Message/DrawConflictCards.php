<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * The target player draws conflict cards, e.g. during the draw phase or to resolve an effect. An empty deck is
 * replaced by the shuffled discard pile.
 */
#[AppAction]
final readonly class DrawConflictCards implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Player who draws.')]
        private string $targetPlayerId,
        #[Description('Number of conflict cards to draw.')]
        private int $quantity,
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
     * The player the effect applies to.
     */
    public function getTargetPlayerId(): string
    {
        return $this->targetPlayerId;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }
}
