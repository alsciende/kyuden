<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * Puts a holding of the dynasty discard pile of its owner into play in one of their provinces, face up, e.g. to resolve
 * an effect (Rebuild). The cards already in the province stay there. While the holding remains, the province is not
 * refilled: a card that has left it during the same effect (`shuffle_into_deck`) is not replaced.
 *
 * The card can belong to any player: the engine does not apply card effects.
 */
#[AppAction(special: true)]
final readonly class PutHoldingIntoProvince implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Holding in a dynasty discard pile.')]
        private string $cardId,
        #[Description('Province of the owner of the holding (not the stronghold province) the holding is put into.')]
        private string $provinceId,
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

    public function getProvinceId(): string
    {
        return $this->provinceId;
    }
}
