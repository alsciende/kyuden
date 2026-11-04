<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * Shuffles a card into the deck of its side, from the deck itself, a hand, play, a province or a discard pile, e.g.
 * to resolve an effect (Rebuild): the card is put into the deck, then the deck is shuffled. Every card of the deck
 * gets a new id. A card leaving play leaves its attachments, tokens and the conflict; a province left without dynasty
 * card is refilled.
 *
 * The card can belong to any player: the engine does not apply card effects.
 */
#[AppAction(special: true)]
final readonly class ShuffleIntoDeck implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Conflict or dynasty card to shuffle into the deck of its owner.')]
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
