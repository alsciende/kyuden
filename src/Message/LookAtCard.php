<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * The sender looks at a card, e.g. a facedown province or a facedown card in a province (Iuchi Wayfinder). The log
 * gives the card to the sender only; nothing is recorded: the card is out of sight again afterwards.
 */
#[AppAction(special: true)]
final readonly class LookAtCard implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Card to look at, of any player.')]
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
