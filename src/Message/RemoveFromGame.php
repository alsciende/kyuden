<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * Removes a card from the game, e.g. to resolve an effect (Karmic Balance, Shadow Step, Stolen Secrets, Trading on the
 * Sand Road): from a deck, a hand, play, a province, a discard pile or from under a card, out of play until an effect
 * brings it back: it is then played (`play_character`, `play_attachment`, the events), put into play
 * (`put_character_into_play`), into a province (`put_card_into_province`), into a hand (`add_card_to_hand`), discarded or
 * put into a deck. Face up, it goes to the cards removed from the game of its owner, which every player sees; face
 * down, to those of the sender, who alone knows it. A card leaving play leaves its attachments, tokens and the conflict;
 * a province left without dynasty card is refilled.
 *
 * The card can belong to any player: the engine does not apply card effects.
 */
#[AppAction(special: true)]
final readonly class RemoveFromGame implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Conflict or dynasty card to remove from the game.')]
        private string $cardId,
        #[Description('Whether the card is removed face down, known to the sender only; face up otherwise.')]
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

    public function isFaceDown(): bool
    {
        return $this->faceDown;
    }
}
