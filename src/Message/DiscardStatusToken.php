<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * Discards the status token of a character in play or a province, by an effect: an honored or dishonored character
 * returns to ordinary status without being dishonored nor honored, so that nothing reacting to that is triggered.
 *
 * The card can belong to any player: the engine does not apply card effects.
 */
#[AppAction(special: true)]
final readonly class DiscardStatusToken implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Character in play or province with a status token.')]
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
