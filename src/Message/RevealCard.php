<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * The sender reveals a card to every player, e.g. a card of their hand, or a card found in a deck they have looked at
 * (Agasha Swordsmith: "reveal it"). The log gives the card to every player; nothing is recorded. A facedown province is
 * turned face up with `reveal_province`, a facedown dynasty card with `reveal_dynasty_card`.
 */
#[AppAction(special: true)]
final readonly class RevealCard implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Card to reveal, of any player.')]
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
