<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The active player plays an attachment during an action window and attaches it to a card in play (a character of any
 * player, or a province). From their hand, or from elsewhere when a card allows it (Guidance of the Ancestors,
 * Kunshu, Infiltrator…): an attachment of a hand, a conflict deck (a card looked at) or a conflict discard pile, of
 * any player. The player controls the attachment, whoever owns it. The player says what they pay (`cardCost`), since
 * card effects may modify costs.
 * The player resolves what follows with other actions, then ends their action (`EndResolution`), which ends
 * their opportunity to act.
 */
#[AppAction]
final readonly class PlayAttachment implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Attachment played: in a hand, a conflict deck or a conflict discard pile.')]
        private string $cardId,
        #[Description('Character in play or province the attachment goes on.')]
        private string $targetCardId,
        #[Description('Fate paid for the card.')]
        #[Assert\PositiveOrZero]
        private int $cardCost,
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

    public function getCardCost(): int
    {
        return $this->cardCost;
    }
}
