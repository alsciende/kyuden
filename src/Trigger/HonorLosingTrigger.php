<?php

declare(strict_types=1);

namespace App\Trigger;

use App\Entity\Player;

/**
 * A player would lose honor: by an effect (LoseHonor), or the defending player for an unopposed conflict
 * (`unopposed_applied`, 3.2.4). Giving honor is not losing it (glossary, Honor): the transfers are not this condition.
 * Cancelled (Ikebana Artisan, Duty), the honor is not lost.
 */
final readonly class HonorLosingTrigger implements ImminentTriggerInterface
{
    public function __construct(
        public Player $player,
        public int $amount,
        /**
         * The honor lost for an unopposed conflict.
         */
        public bool $unopposed = false,
    ) {
    }

    #[\Override]
    public static function getTriggerName(): string
    {
        return 'honor_losing';
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
        return '{player} would lose {amount} honor';
    }

    #[\Override]
    public function getLogParams(): array
    {
        return ['player' => $this->player, 'amount' => $this->amount];
    }
}
