<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use App\Enum\DeckPosition;

/**
 * Puts a card face down on top of the deck of its side or at its bottom, from the deck itself, a hand, play, a
 * province or a discard pile, e.g. to resolve an effect (Hida Tomonatsu, Shrewd Yasuki). A card leaving play leaves
 * its attachments, tokens and the conflict; a province left without dynasty card is refilled.
 *
 * The card can belong to any player: the engine does not apply card effects.
 */
#[AppAction]
final readonly class PutCardOnDeck implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Conflict or dynasty card to put into the deck of its owner.')]
        private string $cardId,
        #[Description('Where the card goes in the deck.')]
        private DeckPosition $position,
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

    public function getPosition(): DeckPosition
    {
        return $this->position;
    }
}
