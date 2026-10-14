<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * The active player triggers an action ability of one of their cards during an action window.
 *
 * The engine does not apply card effects: the message only records which card is used. The player pays the costs
 * and resolves the effects with other actions, then ends their opportunity to act (`EndOpportunity`).
 */
#[AppAction]
final readonly class TriggerActionAbility implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Card whose action ability is triggered.')]
        private string $cardId,
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
}
