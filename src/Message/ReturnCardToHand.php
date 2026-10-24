<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * Returns a card in play (a character, an attachment) to its owner's hand. A dynasty card is put into its owner's
 * dynasty discard pile instead.
 *
 * The card can belong to any player: effects often target an opponent's card.
 */
#[AppAction]
final readonly class ReturnCardToHand implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Card in play to return to its owner\'s hand.')]
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
