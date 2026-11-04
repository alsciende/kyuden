<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * Moves the status token of a character in play to another character in play, by an effect: neither is honored nor
 * dishonored. The source returns to ordinary status; the target takes the status of the token, or returns to ordinary
 * status when it had the other token (both are discarded).
 *
 * The characters can belong to any player: the engine does not apply card effects.
 */
#[AppAction(special: true)]
final readonly class MoveStatusToken implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Honored or dishonored character in play whose status token is moved.')]
        private string $fromCardId,
        #[Description('Another character in play, without a status token of the same kind.')]
        private string $toCardId,
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

    public function getFromCardId(): string
    {
        return $this->fromCardId;
    }

    public function getToCardId(): string
    {
        return $this->toCardId;
    }
}
