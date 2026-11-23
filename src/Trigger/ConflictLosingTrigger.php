<?php

declare(strict_types=1);

namespace App\Trigger;

use App\Entity\Player;

/**
 * A player would lose the conflict (`result_determined`, 3.2.3), and the characters on their side with them.
 * Cancelled (Kakita Toshimoko: "both players count 0 total skill"), the conflict has no winner.
 */
final readonly class ConflictLosingTrigger implements ImminentTriggerInterface
{
    public function __construct(
        public Player $loser,
        public Player $winner,
    ) {
    }

    #[\Override]
    public static function getTriggerName(): string
    {
        return 'conflict_losing';
    }

    #[\Override]
    public static function isResolvedWhenCancelled(): bool
    {
        return false;
    }

    #[\Override]
    public function getTriggerParams(): array
    {
        return ['player' => $this->loser->getId(), 'winner' => $this->winner->getId()];
    }

    #[\Override]
    public function getLogTemplate(): string
    {
        return '{player} would lose the conflict';
    }

    #[\Override]
    public function getLogParams(): array
    {
        return ['player' => $this->loser];
    }
}
