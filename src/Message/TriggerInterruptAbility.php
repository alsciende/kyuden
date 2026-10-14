<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * A player triggers an interrupt ability of one of their cards before its triggering condition resolves, whether or
 * not they have the opportunity to act. Action and reaction abilities are triggered with `TriggerActionAbility` and
 * `TriggerReactionAbility`.
 *
 * The engine does not apply card effects: the message only records which card is used. The player pays the costs
 * and resolves the effects with other actions.
 */
#[AppAction]
final readonly class TriggerInterruptAbility implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Card whose interrupt ability is triggered.')]
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
