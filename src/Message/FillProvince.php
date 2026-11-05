<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * Puts the top card of the dynasty deck of its owner on a province, face down or face up, for the effects that fill a
 * province or add a card to it (Rally, City of the Rich Frog, refilling a province face up): a province a dynasty card
 * has left (played, discarded) is refilled face down by the engine, unless an effect has filled it before.
 *
 * The province can belong to any player, and hold cards already: the engine does not apply card effects.
 */
#[AppAction(special: true)]
final readonly class FillProvince implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Province to fill (not the stronghold province).')]
        private string $provinceId,
        #[Description('Whether the card is put face up; face down by default.')]
        private bool $faceUp = false,
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

    public function getProvinceId(): string
    {
        return $this->provinceId;
    }

    public function isFaceUp(): bool
    {
        return $this->faceUp;
    }
}
