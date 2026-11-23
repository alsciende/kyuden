<?php

declare(strict_types=1);

namespace App\Trigger;

use App\Entity\Player;

/**
 * A duel would resolve, its statistics declared (the second DeclareDuelStatistic). Stay Your Hand cancels the duel
 * itself (CancelDuel), so that the statistic waiting is no longer accepted.
 */
final readonly class DuelResolvingTrigger implements ImminentTriggerInterface
{
    public function __construct(
        public Player $challenger,
        public Player $challenged,
        /**
         * The characters dueling, of both players.
         *
         * @var list<string>
         */
        public array $cardIds,
    ) {
    }

    #[\Override]
    public static function getTriggerName(): string
    {
        return 'duel_resolving';
    }

    #[\Override]
    public static function isResolvedWhenCancelled(): bool
    {
        return false;
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return ['challenger' => $this->challenger->getId(), 'challenged' => $this->challenged->getId()];
    }

    #[\Override]
    public function getLogTemplate(): string
    {
        return 'The duel would resolve';
    }

    #[\Override]
    public function getLogParams(): array
    {
        return [];
    }
}
