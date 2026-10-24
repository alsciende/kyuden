<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * Sacrifices a card in play (a character, an attachment, a holding in a province) to its owner's discard pile, usually
 * as the cost of an ability. Not a discard: effects reacting to a discarded card do not apply.
 *
 * The card can belong to any player: an effect can make an opponent sacrifice a card.
 */
#[AppAction]
final readonly class SacrificeCard implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Card in play to sacrifice.')]
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
