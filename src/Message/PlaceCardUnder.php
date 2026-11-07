<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * Places a card under another card, out of play, e.g. to resolve an effect (Back-Alley Hideaway, Daidoji Uji, Kaiu
 * Shihobu, Under Siege: the hand set aside under the stronghold, then added back to it with `add_card_to_hand`): from a
 * deck, a hand, play, a province, a discard pile or from the cards removed from the game, under a card in play (a
 * character, an attachment, a holding face up in a province, the stronghold) or a province. The cards under a card stay
 * there until an effect takes them (they are played, put into play, into a province or a hand like the cards removed from
 * the game), and are discarded when it leaves play. Face up, every player sees it; face down, only the owner of the card
 * it is under. A card leaving play leaves its attachments, tokens and the conflict; a province left without dynasty card
 * is refilled.
 *
 * The cards can belong to any player: the engine does not apply card effects.
 */
#[AppAction(special: true)]
final readonly class PlaceCardUnder implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Conflict or dynasty card to place under the host card.')]
        private string $cardId,
        #[Description('Card in play (character, attachment, holding face up in a province, stronghold) or province the card is placed under.')]
        private string $hostCardId,
        #[Description('Whether the card is placed face down, known to the owner of the host card only; face up otherwise.')]
        private bool $faceDown,
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

    public function getHostCardId(): string
    {
        return $this->hostCardId;
    }

    public function isFaceDown(): bool
    {
        return $this->faceDown;
    }
}
