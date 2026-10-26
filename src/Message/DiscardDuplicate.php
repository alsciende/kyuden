<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * The active player discards, during the play cards from provinces step, a duplicate of a unique character they have
 * in play (a copy with the same title, from their hand or face up in one of their provinces) to place 1 fate on the
 * character in play.
 * The province is then refilled; ending the action (`EndResolution`), which ends the opportunity to act, is a separate
 * action of the player.
 */
#[AppAction]
final readonly class DiscardDuplicate implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Duplicate discarded: a character in the hand of the player or face up in one of their provinces.')]
        private string $cardId,
        #[Description('Unique character in play of the player, with the same title, on which 1 fate is placed.')]
        private string $copyId,
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

    public function getCopyId(): string
    {
        return $this->copyId;
    }
}
