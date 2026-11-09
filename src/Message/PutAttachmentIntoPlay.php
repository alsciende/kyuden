<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * Puts an attachment into play without playing it, e.g. to resolve an effect (Time for War, Restored Heirloom,
 * Illustrious Forge, Niten): no cost is paid and no opportunity to act is used. The attachment comes from a hand, a
 * conflict deck (a card looked at), a conflict discard pile or from out of play (removed from the game, under a card),
 * and is attached to a character in play of any player or to a province, under the control of its owner or of the
 * player given.
 *
 * The card can belong to any player: the engine does not apply card effects.
 */
#[AppAction(special: true)]
final readonly class PutAttachmentIntoPlay implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Attachment in a hand, a conflict deck, a conflict discard pile, removed from the game or under a card.')]
        private string $cardId,
        #[Description('Character in play or province the attachment goes on.')]
        private string $targetCardId,
        #[Description('Player under whose control the attachment enters play; its owner by default.')]
        private ?string $controllerId = null,
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

    public function getTargetCardId(): string
    {
        return $this->targetCardId;
    }

    public function getControllerId(): ?string
    {
        return $this->controllerId;
    }
}
