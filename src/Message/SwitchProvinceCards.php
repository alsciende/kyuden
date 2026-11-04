<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * Switches two dynasty cards in different provinces of the same player, each keeping its orientation, e.g. to resolve
 * an effect (Borderlands Fortifications).
 *
 * The cards can belong to any player: the engine does not apply card effects.
 */
#[AppAction(special: true)]
final readonly class SwitchProvinceCards implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Dynasty card in a province.')]
        private string $cardId,
        #[Description('Dynasty card in another province of the same player.')]
        private string $otherCardId,
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

    public function getOtherCardId(): string
    {
        return $this->otherCardId;
    }
}
