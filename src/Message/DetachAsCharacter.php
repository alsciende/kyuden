<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * Detaches an attachment from the character it is attached to and turns it into a character in play, ready, at home,
 * under the control of its controller: a character played as an attachment (Promising Youth, when the character it is
 * attached to leaves play), or an attachment (Togashi Hoshi: its skills are its printed modifiers, which the players
 * declare). The cards can belong to any player: the engine does not apply card effects.
 */
#[AppAction(special: true)]
final readonly class DetachAsCharacter implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Attachment attached to a character in play, or a character played as an attachment.')]
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
