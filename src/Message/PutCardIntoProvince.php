<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * Puts a dynasty card into a province of its owner, face up or face down, beside the cards already there, e.g. to
 * resolve an effect: a card of the dynasty deck (known through `look_at_cards`: Walking the Way, Our Foe Does Not
 * Wait), of the dynasty discard pile (Miya Satoshi), of another province (Logistics, Recalled Defenses, Governor's Spy),
 * or a character in play, which leaves play (Master of Many Lifetimes, The Receding Tide). A province the card has left
 * is refilled once the effect is resolved, if it is still empty. The deck is not shuffled: `shuffle_dynasty_deck` afterwards.
 *
 * The card can belong to any player: the engine does not apply card effects.
 */
#[AppAction(special: true)]
final readonly class PutCardIntoProvince implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Dynasty card in a dynasty deck, a dynasty discard pile, a province or in play.')]
        private string $cardId,
        #[Description('Province of the owner of the card the card is put into (the stronghold province included), not the one it is in.')]
        private string $provinceId,
        #[Description('Whether the card is put face up; face down otherwise.')]
        private bool $faceUp,
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

    public function isFaceUp(): bool
    {
        return $this->faceUp;
    }
}
