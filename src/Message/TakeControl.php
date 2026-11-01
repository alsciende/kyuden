<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * Takes control of a character or an attachment in play, e.g. to resolve an effect (Blackmail, Calling in Favors). A
 * character joins the play area of its new controller with its attachments, which keep their controller, and
 * participates in the conflict on the side of its new controller. The control lasts until the card leaves play, or
 * until another player takes control of it (its owner taking it back: the engine does not end effects).
 *
 * The card can belong to any player: the engine does not apply card effects.
 */
#[AppAction]
final readonly class TakeControl implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Character or attachment in play, controlled by another player.')]
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
