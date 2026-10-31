<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use App\Enum\FateHolder;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Moves fate from a holder to another, e.g. to resolve an effect: from a fate pool to a ring ("spend 1 fate to an
 * unclaimed ring"), from a character to its owner's fate pool (Jade Tetsubō), from a character to another, from a fate
 * pool to another ("give your opponent 1 fate"), from a ring to a fate pool (Know the World).
 *
 * A holder is a `player` (their fate pool, by player id), a `card` (a character in play, by card id) or a `ring` (by
 * element). The fate from or to the general token pool is gained, lost, placed or removed with the other messages.
 */
#[AppAction]
final readonly class MoveFate implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Kind of holder the fate comes from.')]
        private FateHolder $source,
        #[Description('Holder the fate comes from: a player id, a card id or a ring element.')]
        private string $sourceId,
        #[Description('Kind of holder the fate goes to.')]
        private FateHolder $target,
        #[Description('Holder the fate goes to: a player id, a card id or a ring element.')]
        private string $targetId,
        #[Description('Fate moved.')]
        #[Assert\Positive]
        private int $amount,
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

    public function getSource(): FateHolder
    {
        return $this->source;
    }

    public function getSourceId(): string
    {
        return $this->sourceId;
    }

    public function getTarget(): FateHolder
    {
        return $this->target;
    }

    public function getTargetId(): string
    {
        return $this->targetId;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }
}
