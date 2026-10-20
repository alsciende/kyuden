<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * A player plays an interrupt event from hand before its triggering condition resolves, whether or not they have the
 * opportunity to act (e.g. Stay Your Hand during a duel initiated by an opponent), and pays its cost (`cardCost`); the event goes to the discard pile. The player resolves
 * its effects with other actions, then ends it (`EndResolution`): the opportunities to act do not change. Action and reaction events are played with `PlayActionEvent` and `PlayReactionEvent`.
 */
#[AppAction]
final readonly class PlayInterruptEvent implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Interrupt event in the hand of the player.')]
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
