<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * Adds a conflict card to the hand of its owner from their conflict deck (a card searched for: Agasha Swordsmith,
 * Shrewd Yasuki) or their conflict discard pile (an ancestral attachment, a card taken back), e.g. to resolve an
 * effect. A card of the deck is known by its id once a player has looked at it (`look_at_cards`).
 *
 * The card can belong to any player: the engine does not apply card effects.
 */
#[AppAction(special: true)]
final readonly class AddCardToHand implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Conflict card in a conflict deck or a conflict discard pile.')]
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
