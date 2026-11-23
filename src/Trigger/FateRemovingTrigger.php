<?php

declare(strict_types=1);

namespace App\Trigger;

use App\Entity\PhysicalCard;
use App\Entity\Player;

/**
 * Fate would be removed from a character in play: by an effect (RemoveFate), or in the fate phase (`fate_removed`,
 * 5.3). Cancelled ("instead, move that fate to your fate pool": Embrace the Void), it is not removed from it.
 */
final readonly class FateRemovingTrigger implements ImminentTriggerInterface
{
    public function __construct(
        public PhysicalCard $card,
        /**
         * The player who controls it.
         */
        public Player $controller,
        public int $amount,
    ) {
    }

    #[\Override]
    public static function getTriggerName(): string
    {
        return 'fate_removing';
    }

    #[\Override]
    public static function isResolvedWhenCancelled(): bool
    {
        return false;
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return ['card' => $this->card->getId(), 'controller' => $this->controller->getId(), 'amount' => (string) $this->amount];
    }

    #[\Override]
    public function getLogTemplate(): string
    {
        return '{amount} fate would be removed from {card}';
    }

    #[\Override]
    public function getLogParams(): array
    {
        return ['card' => $this->card, 'amount' => $this->amount];
    }
}
