<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * A player triggers a reaction ability of one of their cards after its triggering condition has resolved, whether or
 * not they have the opportunity to act. Action and interrupt abilities are triggered with `TriggerActionAbility` and
 * `TriggerInterruptAbility`.
 *
 * The engine does not apply card effects: the message only records which card is used. The player pays the costs
 * and resolves the effects with other actions.
 */
#[AppAction]
final readonly class TriggerReactionAbility implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Card whose reaction ability is triggered.')]
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
