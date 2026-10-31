<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;

/**
 * Moves an attachment in play to another character in play or to a province, e.g. to resolve an effect (Giver of
 * Gifts, Calling in Favors). It keeps its owner.
 *
 * The cards can belong to any player: the engine does not apply card effects.
 */
#[AppAction]
final readonly class MoveAttachment implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Attachment in play.')]
        private string $cardId,
        #[Description('Character in play or province the attachment is moved to.')]
        private string $targetCardId,
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
}
