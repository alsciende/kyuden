<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use App\Enum\StatusToken;

/**
 * Places a status token on a character in play or a province, by an effect, without honoring nor dishonoring it, so
 * that nothing reacting to that is triggered (Final Whisper; a dishonored status token on a province, whose printed
 * text is then treated as blank: Overrun). A card with the other token discards both and returns to ordinary status; a
 * card cannot receive a second token of a kind.
 *
 * The card can belong to any player: the engine does not apply card effects.
 */
#[AppAction(special: true)]
final readonly class PlaceStatusToken implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Character in play or province, without a status token of this kind.')]
        private string $cardId,
        #[Description('Kind of the status token placed.')]
        private StatusToken $token,
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

    public function getToken(): StatusToken
    {
        return $this->token;
    }
}
