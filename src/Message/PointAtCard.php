<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * Points at a card to show it to the opponent, e.g. a card in a discard pile or the target of an ability: it changes
 * nothing in the game, only writes an entry in its log.
 *
 * Any card of a stack every player sees (not a deck nor a hand), of any player; a face-down card is pointed at without
 * showing its face.
 */
#[AppAction]
final readonly class PointAtCard implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Card to point at.')]
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
