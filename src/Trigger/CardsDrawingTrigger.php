<?php

declare(strict_types=1);

namespace App\Trigger;

use App\Entity\Player;

/**
 * A player would draw conflict cards outside of the draw phase (DrawConflictCards). Cancelled (Subterfuge: "discard up
 * to 3 of those cards instead"), they do not draw them: the cards are discarded and drawn with the effect messages.
 */
final readonly class CardsDrawingTrigger implements ImminentTriggerInterface
{
    public function __construct(
        public Player $player,
        public int $amount,
    ) {
    }

    #[\Override]
    public static function getTriggerName(): string
    {
        return 'cards_drawing';
    }

    #[\Override]
    public static function isResolvedWhenCancelled(): bool
    {
        return false;
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return ['player' => $this->player->getId(), 'amount' => (string) $this->amount];
    }

    #[\Override]
    public function getLogTemplate(): string
    {
        return '{player} would draw {amount} cards';
    }

    #[\Override]
    public function getLogParams(): array
    {
        return ['player' => $this->player, 'amount' => $this->amount];
    }
}
