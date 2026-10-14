<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;
use App\Attribute\Description;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The player secretly selects a number on their honor dial (honor bid of the draw phase or of a duel).
 */
#[AppAction]
final readonly class BidHonor implements PlayerActionInterface
{
    public const int MINIMUM_BID = 1;
    public const int MAXIMUM_BID = 5;

    public function __construct(
        private string $gameId,
        private string $playerId,
        #[Description('Number selected on the honor dial.')]
        #[Assert\Range(min: self::MINIMUM_BID, max: self::MAXIMUM_BID)]
        private int $bid,
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

    public function getBid(): int
    {
        return $this->bid;
    }
}
