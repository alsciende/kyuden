<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * Puts a character into play without playing it, e.g. to resolve an effect (Ambush, Charge!, Cavalry Reserves, Keeper
 * Initiate): no cost is paid and no opportunity to act is used. The character comes from the hand of its owner, a
 * province (face up) or a discard pile, and enters play ready, at home or participating in the conflict on the side of
 * its owner. Fate placed on it is a separate action (`place_fate`); a province it leaves is refilled by the engine.
 *
 * The card can belong to any player: the engine does not apply card effects.
 */
#[AppAction]
final readonly class PutCharacterIntoPlay implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Character in a hand, face up in a province, or in a discard pile.')]
        private string $cardId,
        #[Description('Whether the character enters play into the conflict, on the side of its owner.')]
        private bool $intoConflict,
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

    public function isIntoConflict(): bool
    {
        return $this->intoConflict;
    }
}
