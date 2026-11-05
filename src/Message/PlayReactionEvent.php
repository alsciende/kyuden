<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A player plays a reaction event after its triggering condition has resolved, whether or not they have the
 * opportunity to act, and pays its cost (`cardCost`); the event goes to the discard pile of its owner. From their
 * hand, or from elsewhere when a card allows it: an event of a hand, a conflict deck (a card looked at) or a conflict
 * discard pile, of any player. The player resolves its effects with other actions, then ends it (`EndResolution`): the
 * opportunities to act do not change. Action and interrupt events are played with `PlayActionEvent` and
 * `PlayInterruptEvent`.
 */
#[AppAction]
final readonly class PlayReactionEvent implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Reaction event played: in a hand, a conflict deck or a conflict discard pile.')]
        private string $cardId,
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

    public function getCardCost(): int
    {
        return $this->cardCost;
    }
}
