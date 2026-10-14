<?php

declare(strict_types=1);

namespace App\Message;

use App\Attribute\AppAction;

/**
 * The giver gives honor to the recipient: "give your opponent 1 honor" is a transfer from the player, "take 1 honor
 * from that opponent" a transfer from that opponent.
 */
#[AppAction]
final readonly class TransferHonor implements PlayerActionInterface
{
    public function __construct(
        private string $gameId,
        private string $playerId,
        private string $giverId,
        private string $recipientId,
        private int $amount,
    ) {
    }

    #[\Override]
    public function getGameId(): string
    {
        return $this->gameId;
    }

    /**
     * The player giving the honor.
     */
    #[\Override]
    public function getPlayerId(): string
    {
        return $this->playerId;
    }

    /**
     * The player giving the honor.
     */
    public function getGiverId(): string
    {
        return $this->giverId;
    }

    /**
     * The player receiving the honor.
     */
    public function getRecipientId(): string
    {
        return $this->recipientId;
    }

    public function getAmount(): int
    {
        return $this->amount;
    }
}
