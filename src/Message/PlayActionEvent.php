<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The active player plays an action event during an action window and pays its cost (`cardCost`); the event goes to
 * the discard pile of its owner. From their hand, or from elsewhere when a card allows it (Kyuden Isawa, Kunshu,
 * Artisan Academy…): an event of a hand, a conflict deck (a card looked at) or a conflict discard pile, of any player.
 * The player resolves its effects with other actions, then ends their action (`EndResolution`), which ends their
 * opportunity to act. Interrupt and reaction events are played with `PlayInterruptEvent` and `PlayReactionEvent`.
 */
#[AppAction]
final readonly class PlayActionEvent implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Action event played: in a hand, a conflict deck or a conflict discard pile.')]
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
