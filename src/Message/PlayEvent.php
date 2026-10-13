<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;

/**
 * The active player plays an event from hand, during an action window (action events) or a reaction window
 * (reaction and interrupt events), and pays its cost (`cardCost`); the event goes to the discard pile. The player
 * resolves its effects with other actions.
 */
#[AppAction]
final readonly class PlayEvent implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        private string $cardId,
        private int $cardCost,
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

    public function getCardCost(): int
    {
        return $this->cardCost;
    }
}
