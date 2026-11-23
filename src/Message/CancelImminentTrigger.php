<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use App\Enum\Element;

/**
 * Cancels what the interrupt window open is about to make happen, or replaces it ("would… instead": Reprieve, Iron
 * Mine, Stand Your Ground…), or cancels the effects of an event or an ability (Censure, Voice of Honor, Mushin no
 * Shin…): the condition of a card no longer occurs (a character no longer leaves play, nor its attachments with it),
 * or that of a ring (a claimed ring no longer returns to the unclaimed pool: Wholeness of the World), or none of the window
 * when neither is given. A window left without condition closes at once: what waited in it is
 * not done, but an event or an ability, which is still played (its costs paid), its resolution marked cancelled; the
 * replacement itself is applied with the effect messages.
 *
 * Any player of the game can send it: the engine does not apply card effects.
 */
#[AppAction(special: true)]
final readonly class CancelImminentTrigger implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Card whose imminent condition is cancelled (the character that would leave play, the event played or the card whose ability is triggered); every condition of the window when omitted.')]
        private ?string $cardId = null,
        #[Description('Ring whose imminent condition is cancelled (a claimed ring that would be returned), instead of a card.')]
        private ?Element $ring = null,
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

    public function getRing(): ?Element
    {
        return $this->ring;
    }
}
