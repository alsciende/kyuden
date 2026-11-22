<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * Cancels what the interrupt window open is about to make happen, or replaces it ("would… instead": Reprieve, Iron
 * Mine, Stand Your Ground…): the condition of a card no longer occurs (a character no longer leaves play, nor its
 * attachments with it), or none of the window when no card is given. A window left without condition closes at once,
 * and what waited in it is not done; the replacement itself is applied with the effect messages.
 *
 * Any player of the game can send it: the engine does not apply card effects.
 */
#[AppAction(special: true)]
final readonly class CancelImminentTrigger implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Card whose imminent condition is cancelled (the character that would leave play); every condition of the window when omitted.')]
        private ?string $cardId = null,
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

    public function getCardId(): ?string
    {
        return $this->cardId;
    }
}
